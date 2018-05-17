<?php

class Addons extends CCS {
    
	function __construct(){
        if (is_null($this->db)) $this->db = Database::instance();
    }
    
    /**
    * Tests an addon over active addons
    *
    */
    function is_addon_enable($addons = null, $addon_name = null) {
        if (!$addons || !$addon_name || count($addons) == 0) {
            return false;
        }
        
        $result = false;
        foreach ($addons as $v) {
            if ($v['addon_name'] == $addon_name && $v['enabled'] == 1) {
                $result = true;
                break;
            }
        }

        return $result;
    }
}
