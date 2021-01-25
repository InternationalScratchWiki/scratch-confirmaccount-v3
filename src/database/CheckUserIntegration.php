<?php

class CheckUserIntegration {
	/**
	 * Determine if CheckUSer is installed
	 * @return true if installed, false otherwise
	 */
    public static function isLoaded () : bool {
        return ExtensionRegistry::getInstance()->isLoaded('CheckUser');
    }
    
	/**
	 * Get a list of all usernames with checkuser records from a given IP address
	 *
	 * @param ip The IP address being locked up
	 * @param dbr A readable database connection
	 * @return An array of usernames with checkuser records from \p ip, or empty if checkuser is not installed
	 */
    public static function getCUUsernamesFromIP ($ip, IDatabase $dbr) : array {
        if (!self::isLoaded()) {
            return [];
        }
        
        return $dbr->selectFieldValues(
            ['cu_changes', 'user'],
            'DISTINCT user_name',
            ['cuc_ip_hex' => IP::toHex($ip)],
            __METHOD__,
            array(),
            ['user' => ['LEFT JOIN', 'user_id=cuc_user']]
        );
    }
}