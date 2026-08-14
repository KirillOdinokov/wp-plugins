<?php
if ( ! defined( 'ABSPATH' ) ) exit;
class OTV_Plugin_Updater {
    private $s,$f,$u,$v,$n,$a,$au,$d;
    public function __construct($pf,$uu,$cv,$aa=[]){$this->f=$pf;$this->u=$uu;$this->s=plugin_basename($pf);$this->v=$cv;$this->n=$aa['name']??'';$this->a=$aa['author']??'';$this->au=$aa['author_uri']??'';$this->d=$aa['description']??'';add_filter('pre_set_site_transient_update_plugins',[$this,'c']);add_filter('plugins_api',[$this,'i'],20,3);}
    public function c($t){if(empty($t->checked))return $t;$r=$this->l();if(!$r)return $t;if(version_compare($this->v,$r['version'],'>='))return $t;if(empty($r['download_url']))return $t;$t->response[$this->s]=(object)['slug'=>dirname($this->s),'plugin'=>$this->s,'new_version'=>$r['version'],'url'=>$r['homepage']??'','package'=>$r['download_url'],'tested'=>$r['tested']??get_bloginfo('version')];return $t;}
    public function i($res,$act,$args){if('plugin_information'!==$act||!isset($args->slug)||$args->slug!==dirname($this->s))return $res;$r=$this->l();if(!$r)return $res;return(object)['name'=>$this->n,'slug'=>dirname($this->s),'version'=>$r['version'],'author'=>$this->a,'homepage'=>$r['homepage']??$this->au,'requires'=>$r['requires']??'5.8','tested'=>$r['tested']??get_bloginfo('version'),'last_updated'=>$r['last_updated']??'','download_link'=>$r['download_url'],'sections'=>['description'=>$this->d,'changelog'=>$r['changelog']??'']];}
    private function l(){$k='otv_rel_'.md5($this->u);$c=get_transient($k);if(false!==$c)return $c;$resp=wp_remote_get($this->u,['timeout'=>15]);if(is_wp_error($resp)||200!==wp_remote_retrieve_response_code($resp))return null;$d=json_decode(wp_remote_retrieve_body($resp),true);if(!$d||empty($d['version'])||empty($d['download_url']))return null;set_transient($k,$d,6*HOUR_IN_SECONDS);return $d;}
}
