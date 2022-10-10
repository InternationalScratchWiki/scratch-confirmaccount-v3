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
	 * @return CheckUserEntry[] An array of checkuser records from \p ip, or empty if checkuser is not installed
	 */
    public static function getCUUsernamesFromIP ($ip, IDatabase $dbr) : array {
        if (!self::isLoaded()) {
            return [];
        }
        
        $results = $dbr->select(
            ['cu_changes', 'user'],
            [
                'user_name' => 'user_name', 
                'cuc_timestamp' => 'MAX(cuc_timestamp)',
                'user_id' => 'MAX(user_id)'
            ],
            ['cuc_ip_hex' => IP::toHex($ip)],
            __METHOD__,
            [
                'GROUP BY' => 'user_name',
                'ORDER BY' => 'cuc_timestamp DESC'
            ],
            ['user' => ['LEFT JOIN', 'user_id=cuc_user']]
        );

        $entries = [];

        foreach ($results as $row) {
            $entries[] = CheckUserEntry::fromRow($row);
        }

        return $entries;
    }
}

class CheckUserEntry {
    public string $username;
    public int $userId;
    public string $lastTimestamp;

    public function __construct(string $username, int $userId, string $lastTimestamp) {
        $this->username = $username;
        $this->userId = $userId;
        $this->lastTimestamp = $lastTimestamp;
    }

    public static function fromRow($row) : CheckUserEntry {
        return new CheckUserEntry($row->user_name, $row->user_id, $row->cuc_timestamp);
    }
}
