<?php 
/*
Plugin Name: Get API cache
Author: Tomáš Bohata
Description: For faster working with WP-Rest API. Gets info from redis if endopoint is cached or not.
Version: 0.0.1
Author URI: https://www.tre.cz
License: GPLv2 or later
*/
/*
This program is free software; you can redistribute it and/or
modify it under the terms of the GNU General Public License
as published by the Free Software Foundation; either version 2
of the License, or (at your option) any later version.
This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.
You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
*/

//you need to have configured Redis on your server
define("REDIS_HOST", "127.0.0.1:6379");
define("REDIS_DB","1");
define("REDIS_TTL","120");

class rest_api_cache{
	function get_cache(){
	   header("X-Robots-Tag: noindex, nofollow", true);

	//to see endpoint without cache  
	if( isset($_GET['nocache']) && $_GET['nocache'] == true)
        	return;

    $url = "$_SERVER[REQUEST_URI]";

    $uri= explode('/',$url);

    if( $uri[1] !== 'wp-json' || $uri[2] !== 'wp' || $uri[3] !== 'v2'){
        return;
    }
     $endpoint = str_replace('/wp-json/wp/v2/','',$url);

        try{
                $redis = new Redis();
                $redis->connect(REDIS_SERVER);
                $redis->select(REDIS_DB);
        } catch (Exception $e) {
                die($e->getMessage());
        }

        if($redis->get($endpoint)){

        header('Content-Type: application/json');
        $data =  json_decode($redis->get($endpoint),true);
        header('X-WP-Total: '.$data['X-WP-Total']);
        header('X-WP-TotalPages: '.$data['X-WP-TotalPages']);
        echo json_encode($data['data']);
        $redis->close();
  }

}

$cache = new rest_api_cache();
$cache->get_cache();


add_action('rest_post_dispatch', function (WP_HTTP_Response $result, WP_REST_Server $wpServerInstance, WP_REST_Request $wpServerRequest) {
        $headers = $result->get_headers();
        $save_result = $result->get_data();

        $save_array = array();
        $save_array['X-WP-Total'] = $headers['X-WP-Total'];
        $save_array['X-WP-TotalPages'] = $headers['X-WP-TotalPages'];
        $save_array['data'] = $save_result;
        $key = str_replace('/wp-json/wp/v2/','',$_SERVER['REQUEST_URI']);
        if(strpos($key,"?") > 0)        $key = str_replace('?nocache=true','',$key);
        else $key = str_replace('&nocache=true','',$key);
        try{
                $redis = new Redis();
                $redis->connect(REDIS_HOST);
                $redis->select(REDIS_DB);
                $main_keys = get_keys_to_regenerate();
                $redis->set($key, json_encode($save_array), REDIS_TTL);
       } catch (Exception $e) {
               die($e->getMessage());
       }

        return $result;
}, 10, 3);

