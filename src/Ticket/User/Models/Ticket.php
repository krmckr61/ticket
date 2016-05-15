<?php

namespace Ticket\User\Models;

use Ticket\PathFinder;

class Ticket extends PathFinder
{

    public function getTicketsByUser($userId)
    {
        return $this->app['db']->fetchAll('SELECT t.id, t.head, t.description, t.date, t.file, i.name as importance, t.status
                                              FROM tickets as t
                                                INNER JOIN importances AS i ON t.importance=i.id
                                                where t.user=? ORDER BY t.status DESC, t.importance DESC, date ASC', [$userId]);
    }

    public function getChooseCategories($ticketId){
        return $this->app['db']->fetchAll('SELECT c.id, c.name
                                                        FROM ticketcategories AS tc
                                                          INNER JOIN categories AS c ON tc.category=c.id
                                                        where tc.ticket=? group by tc.category', [$ticketId]);
    }

    public function getCategories(){
        return $this->app['db']->fetchAll('SELECT id, name
                                               FROM categories');
    }

    public function getImportances(){
        return $this->app['db']->fetchAll('SELECT id, name
                                               FROM importances');
    }

    public function add($fields){
        $cats = $fields['categories'];
        unset($fields['categories']);
        if($this->app['db']->insert('tickets', $fields)) {
            $ticketId = $this->app['db']->lastInsertId();
            foreach ($cats as $cat) {
                $this->app['db']->insert('ticketcategories', ['category' => $cat, 'ticket' => $ticketId]);
            }
            return true;
        } else {
            return false;
        }
    }

    public function getTicketByUser($userId, $ticketId){
        return $this->app['db']->fetchAssoc('SELECT t.*, i.name AS importance, u.name AS user FROM tickets AS t
                                                INNER JOIN importances AS i ON t.importance=i.id
                                                INNER JOIN users AS u ON t.user=u.id
                                              WHERE t.id=? && t.user=?', [$ticketId, $userId]);
    }

    public function getResponses($id){
        return $this->app['db']->fetchAll('SELECT *, a.name as admin FROM comments AS c
                                                INNER JOIN admins AS a ON c.admin=a.id
                                                WHERE c.ticket=? ORDER BY c.date DESC', [$id]);
    }

}