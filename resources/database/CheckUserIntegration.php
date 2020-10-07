<?php

class CheckUserIntegration {
    public static function isLoaded () {
        return ExtensionRegistry::getInstance()->isLoaded('CheckUser');
    }
    
    public static function getCUUsernamesFromIP ($ip, &$usernames) {
        if (!self::isLoaded()) {
            $usernames = array();
            return;
        }
        
        $dbr = wfGetDB(DB_REPLICA);
        
        $usernames = $dbr->selectFieldValues(
            ['cu_changes', 'user'],
            'DISTINCT user_name',
            ['cuc_ip_hex' => IP::toHex($ip)],
            __METHOD__,
            array(),
            ['user' => ['LEFT JOIN', 'user_id=cuc_user']]
        );
    }
}