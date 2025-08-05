<?php
if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * ExternalRoomLockManager - Prevents room booking conflicts across all booking channels
 * 
 * This class provides a locking mechanism to prevent race conditions when booking rooms
 * across different channels (admin panel, frontend, external API)
 */
class ExternalRoomLockManager
{
    const LOCK_DURATION = 300; // 5 minutes lock duration
    const ADMIN_LOCK_DURATION = 600; // 10 minutes for admin users (longer session)
    
    /**
     * Check if a room is currently locked by any booking process
     * 
     * @param int $roomId
     * @param string $dateFrom
     * @param string $dateTo
     * @return array Lock status information
     */
    public static function checkRoomLock($roomId, $dateFrom, $dateTo)
    {
        $sql = 'SELECT * FROM `'._DB_PREFIX_.'htl_room_booking_locks` 
                WHERE `id_room` = '.(int)$roomId.'
                AND `date_from` < \''.pSQL($dateTo).'\'
                AND `date_to` > \''.pSQL($dateFrom).'\'
                AND `expires_at` > NOW()
                AND `is_active` = 1';
        
        $locks = Db::getInstance()->executeS($sql);
        
        if ($locks) {
            $lock = $locks[0];
            return array(
                'is_locked' => true,
                'locked_by' => $lock['locked_by_type'],
                'lock_id' => $lock['lock_id'],
                'expires_at' => $lock['expires_at'],
                'message' => self::getLockMessage($lock['locked_by_type'])
            );
        }
        
        return array('is_locked' => false);
    }
    
    /**
     * Create a lock on a room to prevent conflicts
     * 
     * @param int $roomId
     * @param string $dateFrom
     * @param string $dateTo
     * @param string $lockedByType (admin|frontend|external_api)
     * @param string $lockedByIdentifier (user ID, session ID, API key, etc.)
     * @return array Lock result
     */
    public static function lockRoom($roomId, $dateFrom, $dateTo, $lockedByType = 'external_api', $lockedByIdentifier = '')
    {
        // First check if room is already locked
        $existingLock = self::checkRoomLock($roomId, $dateFrom, $dateTo);
        if ($existingLock['is_locked']) {
            return array(
                'success' => false,
                'error' => 'Room is currently being processed by another user/system',
                'lock_info' => $existingLock
            );
        }
        
        $lockDuration = ($lockedByType === 'admin') ? self::ADMIN_LOCK_DURATION : self::LOCK_DURATION;
        $lockId = self::generateLockId();
        
        $result = Db::getInstance()->insert('htl_room_booking_locks', array(
            'lock_id' => pSQL($lockId),
            'id_room' => (int)$roomId,
            'date_from' => pSQL($dateFrom),
            'date_to' => pSQL($dateTo),
            'locked_by_type' => pSQL($lockedByType),
            'locked_by_identifier' => pSQL($lockedByIdentifier),
            'created_at' => date('Y-m-d H:i:s'),
            'expires_at' => date('Y-m-d H:i:s', time() + $lockDuration),
            'is_active' => 1
        ));
        
        if ($result) {
            return array(
                'success' => true,
                'lock_id' => $lockId,
                'expires_at' => date('Y-m-d H:i:s', time() + $lockDuration)
            );
        }
        
        return array(
            'success' => false,
            'error' => 'Failed to create room lock'
        );
    }
    
    /**
     * Release a room lock
     * 
     * @param string $lockId
     * @return bool
     */
    public static function releaseLock($lockId)
    {
        return Db::getInstance()->update('htl_room_booking_locks', 
            array('is_active' => 0, 'released_at' => date('Y-m-d H:i:s')),
            'lock_id = \''.pSQL($lockId).'\''
        );
    }
    
    /**
     * Clean up expired locks
     */
    public static function cleanupExpiredLocks()
    {
        Db::getInstance()->execute('UPDATE `'._DB_PREFIX_.'htl_room_booking_locks` 
                                  SET `is_active` = 0 
                                  WHERE `expires_at` < NOW() AND `is_active` = 1');
    }
    
    /**
     * Check if multiple rooms are available and lock them atomically
     * 
     * @param array $rooms Array of room booking data
     * @param string $lockedByType
     * @param string $lockedByIdentifier
     * @return array
     */
    public static function lockMultipleRooms($rooms, $lockedByType = 'external_api', $lockedByIdentifier = '')
    {
        // Clean up expired locks first
        self::cleanupExpiredLocks();
        
        $lockIds = array();
        $conflicts = array();
        
        // Start transaction for atomic locking
        Db::getInstance()->execute('START TRANSACTION');
        
        try {
            // Check all rooms for conflicts first
            foreach ($rooms as $room) {
                $lock = self::checkRoomLock($room['id_room'], $room['date_from'], $room['date_to']);
                if ($lock['is_locked']) {
                    $conflicts[] = array(
                        'room_id' => $room['id_room'],
                        'lock_info' => $lock
                    );
                }
            }
            
            if (!empty($conflicts)) {
                Db::getInstance()->execute('ROLLBACK');
                return array(
                    'success' => false,
                    'error' => 'One or more rooms are currently unavailable',
                    'conflicts' => $conflicts
                );
            }
            
            // Lock all rooms
            foreach ($rooms as $room) {
                $lockResult = self::lockRoom($room['id_room'], $room['date_from'], $room['date_to'], $lockedByType, $lockedByIdentifier);
                if ($lockResult['success']) {
                    $lockIds[] = $lockResult['lock_id'];
                } else {
                    // If any lock fails, rollback all
                    Db::getInstance()->execute('ROLLBACK');
                    return array(
                        'success' => false,
                        'error' => 'Failed to lock room ID: ' . $room['id_room']
                    );
                }
            }
            
            Db::getInstance()->execute('COMMIT');
            
            return array(
                'success' => true,
                'lock_ids' => $lockIds
            );
            
        } catch (Exception $e) {
            Db::getInstance()->execute('ROLLBACK');
            return array(
                'success' => false,
                'error' => 'Transaction failed: ' . $e->getMessage()
            );
        }
    }
    
    /**
     * Generate a unique lock ID
     * 
     * @return string
     */
    private static function generateLockId()
    {
        return 'lock_' . uniqid() . '_' . time();
    }
    
    /**
     * Get user-friendly lock message
     * 
     * @param string $lockedByType
     * @return string
     */
    private static function getLockMessage($lockedByType)
    {
        switch ($lockedByType) {
            case 'admin':
                return 'This room is currently being processed by an administrator';
            case 'frontend':
                return 'This room is currently being booked by another customer';
            case 'external_api':
                return 'This room is currently being processed through the booking API';
            default:
                return 'This room is currently unavailable';
        }
    }
    
    /**
     * Create the room locks table if it doesn't exist
     */
    public static function createLocksTable()
    {
        $sql = 'CREATE TABLE IF NOT EXISTS `'._DB_PREFIX_.'htl_room_booking_locks` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `lock_id` varchar(100) NOT NULL,
            `id_room` int(11) NOT NULL,
            `date_from` date NOT NULL,
            `date_to` date NOT NULL,
            `locked_by_type` enum("admin","frontend","external_api") NOT NULL,
            `locked_by_identifier` varchar(255) DEFAULT NULL,
            `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `expires_at` timestamp NOT NULL DEFAULT "0000-00-00 00:00:00",
            `released_at` timestamp NULL DEFAULT NULL,
            `is_active` tinyint(1) NOT NULL DEFAULT "1",
            PRIMARY KEY (`id`),
            UNIQUE KEY `lock_id` (`lock_id`),
            KEY `idx_room_dates` (`id_room`, `date_from`, `date_to`),
            KEY `idx_active_locks` (`is_active`, `expires_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8;';
        
        return Db::getInstance()->execute($sql);
    }
}