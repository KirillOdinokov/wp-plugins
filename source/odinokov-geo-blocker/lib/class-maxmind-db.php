<?php
/**
 * MaxMind GeoLite2 / GeoIP2 Binary DB Reader (pure PHP).
 *
 * Корректно читает стандартный формат MaxMind DB (.mmdb):
 *  - поисковое дерево (record_size 24/28/32),
 *  - секцию данных,
 *  - метаданные в конце файла (маркер "\xAB\xCD\xEFMaxMind.com").
 *
 * Безопасен: при повреждённом/несовместимом файле бросает исключение
 * (вызывающий код ловит и работает fail-open), не зацикливается
 * (ограничение глубины) и не сыплет notice'ами.
 */
class MaxMind_DB_Reader {

	private $data;
	private $metadata = array();
	private $node_count = 0;
	private $record_size = 24;
	private $node_byte_size = 6;
	private $tree_size = 0;
	private $data_start = 0;
	private $pointer_base = 0;
	private $ipv4_start = 0;

	public function __construct( $filepath ) {
		if ( ! is_string( $filepath ) || ! file_exists( $filepath ) ) {
			throw new Exception( 'MaxMind DB file not found: ' . $filepath );
		}
		$data = @file_get_contents( $filepath );
		if ( false === $data || strlen( $data ) < 16 ) {
			throw new Exception( 'Cannot read MaxMind DB file: ' . $filepath );
		}
		$this->data = $data;
		$this->parse_metadata();
	}

	private function parse_metadata() {
		$marker = "\xab\xcd\xefMaxMind.com";
		$pos    = strrpos( $this->data, $marker );
		if ( false === $pos ) {
			throw new Exception( 'Invalid MaxMind DB: metadata marker not found' );
		}
		$decoder        = new MaxMind_DB_Decoder( $this->data, $pos + strlen( $marker ), 0 );
		$this->metadata = $decoder->decode();
		if ( ! is_array( $this->metadata ) || ! isset( $this->metadata['node_count'] ) || ! isset( $this->metadata['record_size'] ) ) {
			throw new Exception( 'Invalid MaxMind DB metadata' );
		}

		$this->node_count     = (int) $this->metadata['node_count'];
		$this->record_size    = (int) $this->metadata['record_size'];
		$this->node_byte_size = (int) ( $this->record_size / 4 );
		if ( $this->node_byte_size < 6 || $this->node_byte_size > 8 || $this->node_count <= 0 ) {
			throw new Exception( 'Unsupported MaxMind DB layout' );
		}
		$this->tree_size     = $this->node_count * $this->node_byte_size;
		$this->data_start    = $this->tree_size;
		$this->pointer_base  = $this->tree_size + 16;

		$ip_version = isset( $this->metadata['ip_version'] ) ? (int) $this->metadata['ip_version'] : 6;
		if ( 6 === $ip_version ) {
			$node = 0;
			for ( $i = 0; $i < 96; $i++ ) {
				$node = $this->read_node( $node, 0 );
			}
			$this->ipv4_start = $node;
		} else {
			$this->ipv4_start = 0;
		}
	}

	private function read_node( $node, $index ) {
		$offset = $node * $this->node_byte_size;
		if ( $offset < 0 || $offset + $this->node_byte_size > $this->tree_size ) {
			return 0;
		}

		if ( 24 === $this->record_size ) {
			if ( 0 === $index ) {
				return ( ord( $this->data[ $offset ] ) << 16 ) | ( ord( $this->data[ $offset + 1 ] ) << 8 ) | ord( $this->data[ $offset + 2 ] );
			}
			return ( ord( $this->data[ $offset + 3 ] ) << 16 ) | ( ord( $this->data[ $offset + 4 ] ) << 8 ) | ord( $this->data[ $offset + 5 ] );
		}

		if ( 28 === $this->record_size ) {
			$b0 = ord( $this->data[ $offset ] );
			$b1 = ord( $this->data[ $offset + 1 ] );
			$b2 = ord( $this->data[ $offset + 2 ] );
			$b3 = ord( $this->data[ $offset + 3 ] );
			$b4 = ord( $this->data[ $offset + 4 ] );
			$b5 = ord( $this->data[ $offset + 5 ] );
			$b6 = ord( $this->data[ $offset + 6 ] );
			if ( 0 === $index ) {
				return ( $b0 << 20 ) | ( $b1 << 12 ) | ( $b2 << 4 ) | ( $b3 >> 4 );
			}
			return ( ( $b3 & 0x0f ) << 24 ) | ( $b4 << 16 ) | ( $b5 << 8 ) | $b6;
		}

		// record_size 32
		if ( 0 === $index ) {
			return ( ord( $this->data[ $offset ] ) << 24 ) | ( ord( $this->data[ $offset + 1 ] ) << 16 ) | ( ord( $this->data[ $offset + 2 ] ) << 8 ) | ord( $this->data[ $offset + 3 ] );
		}
		return ( ord( $this->data[ $offset + 4 ] ) << 24 ) | ( ord( $this->data[ $offset + 5 ] ) << 16 ) | ( ord( $this->data[ $offset + 6 ] ) << 8 ) | ord( $this->data[ $offset + 7 ] );
	}

	public function lookup( $ip_address ) {
		$packed = @inet_pton( $ip_address );
		if ( false === $packed || ( strlen( $packed ) !== 4 && strlen( $packed ) !== 16 ) ) {
			return null;
		}

		$bits = '';
		$len  = strlen( $packed );
		for ( $i = 0; $i < $len; $i++ ) {
			$bits .= str_pad( decbin( ord( $packed[ $i ] ) ), 8, '0', STR_PAD_LEFT );
		}

		if ( 4 === $len ) {
			$node = $this->ipv4_start;
		} else {
			$node = 0;
		}

		$bits_len = strlen( $bits );
		for ( $i = 0; $i < $bits_len; $i++ ) {
			if ( $node >= $this->node_count ) {
				break;
			}
			$node = $this->read_node( $node, (int) $bits[ $i ] );
		}

		if ( $node <= $this->node_count ) {
			// $node === node_count — «нет данных»; < node_count — не дошли до записи.
			return null;
		}

		$offset = $node - $this->node_count + $this->data_start;
		if ( $offset < 0 || $offset >= strlen( $this->data ) ) {
			return null;
		}
		$decoder = new MaxMind_DB_Decoder( $this->data, $offset, $this->pointer_base );
		try {
			return $decoder->decode();
		} catch ( Exception $e ) {
			return null;
		}
	}

	public function get_country_code( $ip_address ) {
		$data = $this->lookup( $ip_address );
		if ( ! is_array( $data ) ) {
			return null;
		}
		if ( isset( $data['country']['iso_code'] ) && is_string( $data['country']['iso_code'] ) ) {
			return $data['country']['iso_code'];
		}
		if ( isset( $data['registered_country']['iso_code'] ) && is_string( $data['registered_country']['iso_code'] ) ) {
			return $data['registered_country']['iso_code'];
		}
		return null;
	}

	public function get_metadata() {
		return $this->metadata;
	}

	public function get_build_epoch() {
		return isset( $this->metadata['build_epoch'] ) ? (int) $this->metadata['build_epoch'] : 0;
	}
}

class MaxMind_DB_Decoder {

	private $data;
	private $offset;
	private $base;
	private $depth = 0;

	public function __construct( $data, $offset, $base = 0 ) {
		$this->data   = $data;
		$this->offset = $offset;
		$this->base   = $base;
	}

	public function decode() {
		if ( ++$this->depth > 200 ) {
			$this->depth--;
			throw new Exception( 'MaxMind DB decode depth exceeded' );
		}
		if ( $this->offset < 0 || $this->offset >= strlen( $this->data ) ) {
			$this->depth--;
			throw new Exception( 'MaxMind DB decode out of bounds' );
		}

		$ctrl = ord( $this->data[ $this->offset ] );
		$this->offset++;
		$type = $ctrl >> 5;

		if ( 0 === $type ) {
			// Extended type.
			if ( $this->offset >= strlen( $this->data ) ) {
				$this->depth--;
				throw new Exception( 'MaxMind DB truncated extended type' );
			}
			$type = ord( $this->data[ $this->offset ] ) + 7;
			$this->offset++;
		}

		if ( 1 === $type ) {
			$result = $this->decode_pointer( $ctrl );
		} else {
			$size = $this->decode_size( $ctrl );
			switch ( $type ) {
				case 2:  $result = $this->read_bytes( $size ); break;                 // utf8_string
				case 3:  $result = $this->decode_double( $size ); break;              // double
				case 4:  $result = $this->read_bytes( $size ); break;                 // bytes
				case 5:                                                                 // uint16
				case 6:                                                                 // uint32
				case 9:                                                                 // uint64
				case 10: $result = $this->decode_uint( $size ); break;                // uint128
				case 7:  $result = $this->decode_map( $size ); break;                 // map
				case 8:  $result = $this->decode_int32( $size ); break;               // int32
				case 11: $result = $this->decode_array( $size ); break;               // array
				case 14: $result = (bool) ( $ctrl & 0x1 ); break;                     // boolean
				case 15: $result = $this->decode_float( $size ); break;               // float
				default: $result = null;
			}
		}

		$this->depth--;
		return $result;
	}

	private function decode_size( $ctrl ) {
		$size = $ctrl & 0x1f;
		if ( $size < 29 ) {
			return $size;
		}
		if ( 29 === $size ) {
			$size = 29 + ord( $this->data[ $this->offset ] );
			$this->offset += 1;
			return $size;
		}
		if ( 30 === $size ) {
			$size = 285 + ( ( ord( $this->data[ $this->offset ] ) << 8 ) | ord( $this->data[ $this->offset + 1 ] ) );
			$this->offset += 2;
			return $size;
		}
		$size = 65821 + ( ( ord( $this->data[ $this->offset ] ) << 16 ) | ( ord( $this->data[ $this->offset + 1 ] ) << 8 ) | ord( $this->data[ $this->offset + 2 ] ) );
		$this->offset += 3;
		return $size;
	}

	private function read_bytes( $size ) {
		$str = substr( $this->data, $this->offset, $size );
		$this->offset += $size;
		return $str;
	}

	private function decode_uint( $size ) {
		$value = 0;
		for ( $i = 0; $i < $size; $i++ ) {
			$value = ( $value << 8 ) | ord( $this->data[ $this->offset + $i ] );
		}
		$this->offset += $size;
		return $value;
	}

	private function decode_int32( $size ) {
		$value = 0;
		for ( $i = 0; $i < $size; $i++ ) {
			$value = ( $value << 8 ) | ord( $this->data[ $this->offset + $i ] );
		}
		$this->offset += $size;
		if ( $size > 0 && $size < 4 && ( $value & ( 1 << ( $size * 8 - 1 ) ) ) ) {
			$value -= ( 1 << ( $size * 8 ) );
		} elseif ( 4 === $size && $value > 0x7fffffff ) {
			$value -= 0x100000000;
		}
		return $value;
	}

	private function decode_double( $size ) {
		$raw = substr( $this->data, $this->offset, 8 );
		$this->offset += $size;
		$u = unpack( 'E', $raw );
		return isset( $u[1] ) ? $u[1] : 0.0;
	}

	private function decode_float( $size ) {
		$raw = substr( $this->data, $this->offset, 4 );
		$this->offset += $size;
		$u = unpack( 'G', $raw );
		return isset( $u[1] ) ? $u[1] : 0.0;
	}

	private function decode_map( $size ) {
		$result = array();
		for ( $i = 0; $i < $size; $i++ ) {
			$key   = $this->decode();
			$value = $this->decode();
			if ( is_string( $key ) || is_int( $key ) ) {
				$result[ $key ] = $value;
			}
		}
		return $result;
	}

	private function decode_array( $size ) {
		$result = array();
		for ( $i = 0; $i < $size; $i++ ) {
			$result[] = $this->decode();
		}
		return $result;
	}

	private function decode_pointer( $ctrl ) {
		$size  = ( $ctrl >> 3 ) & 0x3;
		$value = $ctrl & 0x7;
		$count = $size + 1;
		for ( $i = 0; $i < $count; $i++ ) {
			$value = ( $value << 8 ) | ord( $this->data[ $this->offset ] );
			$this->offset++;
		}

		$target = $this->base + $value;
		if ( $target < 0 || $target >= strlen( $this->data ) ) {
			return null;
		}

		$saved         = $this->offset;
		$this->offset  = $target;
		$result        = $this->decode();
		$this->offset  = $saved;
		return $result;
	}
}
