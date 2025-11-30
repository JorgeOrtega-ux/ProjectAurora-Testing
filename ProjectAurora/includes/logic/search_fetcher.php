<?php
// includes/logic/search_fetcher.php

class SearchFetcher {

    /**
     * Busca usuarios y calcula el estado de amistad y amigos en común.
     * @param PDO $pdo Conexión a la base de datos.
     * @param int $currentUserId ID del usuario que realiza la búsqueda.
     * @param string $query Término de búsqueda.
     * @param int $offset Desplazamiento para paginación.
     * @param int $limit Límite de resultados.
     * @return array ['results' => array, 'hasMore' => bool]
     */
    public static function searchUsers($pdo, $currentUserId, $query, $offset = 0, $limit = 5) {
        $results = [];
        $hasMore = false;
        
        if (trim($query) === '') {
            return ['results' => [], 'hasMore' => false];
        }

        $queryLimit = $limit + 1;

        try {
            // [MODIFICADO] 
            // 1. Se agrega campo 'is_blocked_by_me'
            // 2. Se elimina la exclusión de usuarios que YO bloqueé.
            // 3. Se mantiene la exclusión de usuarios que ME bloquearon a mí.
            $sql = "SELECT u.id, u.username, u.profile_picture, u.role, 
                           f.status as friend_status, f.sender_id,
                           COALESCE(up.message_privacy, 'friends') as message_privacy,
                           (
                               SELECT COUNT(*) 
                               FROM friendships fA 
                               JOIN friendships fB 
                               ON (CASE WHEN fA.sender_id = ? THEN fA.receiver_id ELSE fA.sender_id END) = 
                                  (CASE WHEN fB.sender_id = u.id THEN fB.receiver_id ELSE fB.sender_id END)
                               WHERE (fA.sender_id = ? OR fA.receiver_id = ?) AND fA.status = 'accepted'
                               AND (fB.sender_id = u.id OR fB.receiver_id = u.id) AND fB.status = 'accepted'
                           ) as mutual_friends,
                           (SELECT COUNT(*) FROM user_blocks WHERE blocker_id = ? AND blocked_id = u.id) as is_blocked_by_me
                    FROM users u
                    LEFT JOIN user_preferences up ON u.id = up.user_id
                    LEFT JOIN friendships f 
                    ON (f.sender_id = ? AND f.receiver_id = u.id) 
                    OR (f.sender_id = u.id AND f.receiver_id = ?)
                    WHERE u.username LIKE ? 
                    AND u.id != ? 
                    AND u.account_status = 'active'
                    AND u.id NOT IN (
                        SELECT blocker_id FROM user_blocks WHERE blocked_id = ?
                    )
                    LIMIT $queryLimit OFFSET $offset";

            $stmt = $pdo->prepare($sql);
            
            $stmt->execute([
                $currentUserId, $currentUserId, $currentUserId, // mutual_friends
                $currentUserId,                                 // is_blocked_by_me
                $currentUserId, $currentUserId,                 // join friendships
                '%' . $query . '%',                             // like username
                $currentUserId,                                 // id != self
                $currentUserId                                  // not in (ellos me bloquearon)
            ]);

            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (count($results) > $limit) {
                $hasMore = true;
                array_pop($results); 
            }

        } catch (PDOException $e) {
            error_log("Error en SearchFetcher: " . $e->getMessage());
            return ['results' => [], 'hasMore' => false];
        }

        return [
            'results' => $results,
            'hasMore' => $hasMore
        ];
    }
}
?>