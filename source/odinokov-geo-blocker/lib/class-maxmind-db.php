<?php
/**
 * MaxMind GeoLite2 Binary DB Reader
 * Reads country code from .mmdb file without external PHP extensions.
 */
class MaxMind_DB_Reader {

	private $handle;
	private $metadata;
	private $data_section_start;
	private $ipv4_start_node;

	public function __construct( $filepath ) {
		if ( ! file_exists( $filepath ) ) {
			throw new Exception( 'MaxMind DB file not found: ' . $filepath );
		}
		$this->handle = fopen( $filepath, 'rb' );
		if ( ! $this->handle ) {
			throw new Exception( 'Cannot open MaxMind DB file: ' . $filepath );
		}
		$this->parse_metadata();
	}

	private function parse_metadata() {
		$header = fread( $this->handle, 4 );
		if ( strlen( $header ) < 4 ) {
			throw new Exception( 'Invalid MaxMind DB file' );
		}
		$metadata_section_size = unpack( 'N', $header )[1];
		$raw_metadata = fread( $this->handle, $metadata_section_size );
		$decoder = new MaxMind_DB_Decoder( $raw_metadata, 0 );
		$this->metadata = $decoder->decode();
		$this->data_section_start = ftell( $this->handle );

		$node_count = $this->metadata['node_count'];
		$node_byte_size = $this->metadata['record_size'] * 2 / 8;
		$search_tree_size = $node_count * $node_byte_size;

		$ip_version = isset( $this->metadata['ip_version'] ) ? $this->metadata['ip_version'] : 6;
		if ( $ip_version === 6 ) {
			$node = 0;
			for ( $i = 0; $i < 96; $i++ ) {
				$node = $this->read_node( $node, 0 );
			}
			$this->ipv4_start_node = $node;
		} else {
			$this->ipv4_start_node = 0;
		}
	}

	private function read_node( $node_number, $index ) {
		$node_byte_size = $this->metadata['record_size'] * 2 / 8;
		$offset = $node_number * $node_byte_size + $this->data_section_start;
		fseek( $this->handle, $offset );
		$raw = fread( $this->handle, $node_byte_size );
		if ( strlen( $raw ) < $node_byte_size ) {
			return 0;
		}
		$record_size = $this->metadata['record_size'];
		$bits = '';
		for ( $i = 0; $i < strlen( $raw ); $i++ ) {
			$bits .= str_pad( decbin( ord( $raw[ $i ] ) ), 8, '0', STR_PAD_LEFT );
		}
		$left = bindec( substr( $bits, 0, $record_size ) );
		$right = bindec( substr( $bits, $record_size, $record_size ) );
		return $index === 0 ? $left : $right;
	}

	private function resolve_data_pointer( $pointer, &$out_offset ) {
		$data_section_offset = $this->data_section_start + $this->metadata['node_count'] * ( $this->metadata['record_size'] * 2 / 8 );
		$out_offset = $pointer + $data_section_offset;
	}

	public function lookup( $ip_address ) {
		$packed = inet_pton( $ip_address );
		if ( ! $packed ) {
			return null;
		}
		$bits = '';
		for ( $i = 0; $i < strlen( $packed ); $i++ ) {
			$bits .= str_pad( decbin( ord( $packed[ $i ] ) ), 8, '0', STR_PAD_LEFT );
		}

		$is_ipv4 = ( strlen( $packed ) === 4 );
		if ( $is_ipv4 ) {
			$bits = str_pad( $bits, 128, '0', STR_PAD_LEFT );
		}

		$node = 0;
		$node_count = $this->metadata['node_count'];
		$record_size = $this->metadata['record_size'];

		for ( $i = 0; $i < strlen( $bits ); $i++ ) {
			$bit = (int) $bits[ $i ];
			$next = $this->read_node( $node, $bit );
			if ( $next >= $node_count ) {
				$data_offset = 0;
				$this->resolve_data_pointer( $next - $node_count, $data_offset );
				fseek( $this->handle, $data_offset );
				$raw_data = fread( $this->handle, 1024 );
				$decoder = new MaxMind_DB_Decoder( $raw_data, 0 );
				return $decoder->decode();
			}
			$node = $next;
		}
		return null;
	}

	public function get_country_code( $ip_address ) {
		$data = $this->lookup( $ip_address );
		if ( isset( $data['country']['iso_code'] ) ) {
			return $data['country']['iso_code'];
		}
		if ( isset( $data['registered_country']['iso_code'] ) ) {
			return $data['registered_country']['iso_code'];
		}
		return null;
	}

	public function __destruct() {
		if ( $this->handle ) {
			fclose( $this->handle );
		}
	}
}

class MaxMind_DB_Decoder {

	private $data;
	private $offset;

	public function __construct( $data, $offset ) {
		$this->data = $data;
		$this->offset = $offset;
	}

	public function decode() {
		$ctrl_byte = ord( $this->data[ $this->offset ] );
		$this->offset++;
		$type = $ctrl_byte >> 5;

		switch ( $type ) {
			case 1: // map
				return $this->decode_map( $ctrl_byte );
			case 2: // array
				return $this->decode_array( $ctrl_byte );
			case 3: // boolean
				return $this->decode_boolean( $ctrl_byte );
			case 4: // utf8_string
				return $this->decode_string( $ctrl_byte );
			case 5: // double
				return $this->decode_double( $ctrl_byte );
			case 6: // bytes
				return $this->decode_bytes( $ctrl_byte );
			case 7: // uint16, uint32, uint64, uint128
				return $this->decode_uint( $ctrl_byte );
			case 0: // pointer
				return $this->decode_pointer( $ctrl_byte );
			default:
				return null;
		}
	}

	private function size_from_ctrl( $ctrl_byte, $type, &$size ) {
		$size = $ctrl_byte & 0x1f;
		if ( $size <= 28 ) {
			return;
		}
		$extra_bytes = $size - 28;
		$size = 0;
		for ( $i = 0; $i < $extra_bytes; $i++ ) {
			$size = ( $size << 8 ) | ord( $this->data[ $this->offset + $i ] );
		}
		$this->offset += $extra_bytes;
	}

	private function decode_map( $ctrl_byte ) {
		$count = 0;
		$this->size_from_ctrl( $ctrl_byte, 1, $count );
		$result = array();
		for ( $i = 0; $i < $count; $i++ ) {
			$key = $this->decode();
			$value = $this->decode();
			$result[ $key ] = $value;
		}
		return $result;
	}

	private function decode_array( $ctrl_byte ) {
		$count = 0;
		$this->size_from_ctrl( $ctrl_byte, 2, $count );
		$result = array();
		for ( $i = 0; $i < $count; $i++ ) {
			$result[] = $this->decode();
		}
		return $result;
	}

	private function decode_boolean( $ctrl_byte ) {
		return (bool) ( $ctrl_byte & 0x1f );
	}

	private function decode_string( $ctrl_byte ) {
		$len = 0;
		$this->size_from_ctrl( $ctrl_byte, 4, $len );
		$str = substr( $this->data, $this->offset, $len );
		$this->offset += $len;
		return $str;
	}

	private function decode_double( $ctrl_byte ) {
		$len = 0;
		$this->size_from_ctrl( $ctrl_byte, 5, $len );
		if ( $len === 8 ) {
			$raw = substr( $this->data, $this->offset, 8 );
			$this->offset += 8;
			$unpacked = unpack( 'E', $raw );
			return $unpacked[1];
		}
		$this->offset += $len;
		return 0.0;
	}

	private function decode_bytes( $ctrl_byte ) {
		$len = 0;
		$this->size_from_ctrl( $ctrl_byte, 6, $len );
		$bytes = substr( $this->data, $this->offset, $len );
		$this->offset += $len;
		return $bytes;
	}

	private function decode_uint( $ctrl_byte ) {
		$len = 0;
		$this->size_from_ctrl( $ctrl_byte, 7, $len );
		$val = 0;
		for ( $i = 0; $i < $len; $i++ ) {
			$val = ( $val << 8 ) | ord( $this->data[ $this->offset + $i ] );
		}
		$this->offset += $len;
		return $val;
	}

	private function decode_pointer( $ctrl_byte ) {
		$size = ( $ctrl_byte >> 3 ) & 0x3;
		$offset = $ctrl_byte & 0x7;
		for ( $i = 0; $i < $size; $i++ ) {
			$offset = ( $offset << 8 ) | ord( $this->data[ $this->offset + $i ] );
		}
		$this->offset += $size;
		$saved_offset = $this->offset;
		$this->offset = $offset;
		$result = $this->decode();
		$this->offset = $saved_offset;
		return $result;
	}
}
