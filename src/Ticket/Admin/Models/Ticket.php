<?php

namespace Ticket\Admin\Models;

use Ticket\PathFinder;

class Ticket extends PathFinder
{


    public $searchFields = ['head', 'importance', 'category', 'date'];

    public function getTickets($fields)
    {
        $where = [];
        $inner = [];
        if (count($fields) > 0) {
            foreach($fields as $key => $field) {
                if($key == 'head') {
                    $where[] = "t.head like '%{$field['value']}%'";
                } else if($key == 'importance'){
                    $where[] = "t.importance='{$field['value']}'";
                } else if($key == 'category'){
                    $inner[] = "INNER JOIN ticketcategories AS tc ON t.id=tc.ticket";
                    $where[] = "tc.category='{$field['value']}'";
                } else if($key == 'date') {
                    $where[] = "DATE_FORMAT(t.date, '%d.%m.%Y')='{$field['value']}'";
                }
            }
        }
        $sql = 'SELECT t.id, t.head, t.status, t.description, u.name as user, t.date, t.file, i.name AS importance
                                              FROM tickets AS t
                                                INNER JOIN importances AS i ON t.importance=i.id
                                                INNER JOIN users AS u ON t.user=u.id
                                              ';

        if(count($inner) > 0) {
            $inner = implode(' ', $inner);
            $sql .= $inner;
        }
        if(count($where) > 0) {
            $where = implode(' && ', $where);
            $sql .= " WHERE " . $where;
        }

        $sql .= " ORDER BY t.status DESC, t.importance DESC, date ASC";

        return $this->app['db']->fetchAll($sql);
    }

    public function getCategories()
    {
        return $this->app['db']->fetchAll('SELECT * FROM categories ORDER BY id');
    }

    public function getChooseCategories($ticketId)
    {
        return $this->app['db']->fetchAll('SELECT c.id, c.name
                                                        FROM ticketcategories AS tc
                                                          inner join categories as c on tc.category=c.id
                                                        where tc.ticket=? group by tc.category', [$ticketId]);
    }

    public function getImportances()
    {
        return $this->app['db']->fetchAll('SELECT id, name
                                               FROM importances');
    }

    public function getDates()
    {
        return $this->app['db']->fetchAll('SELECT DATE_FORMAT(date, \'%d.%m.%Y\') AS date FROM tickets GROUP BY DATE_FORMAT(date, \'%d.%m.%Y\') ORDER BY date DESC');
    }

    public function haveCategory($name) {
        return $this->app['db']->fetchAssoc('SELECT id FROM categories WHERE name=?', [$name]);
    }

    public function addCategory($name){
        if($this->app['db']->insert('categories', ['name' => $name])) {
            return true;
        } else {
            return false;
        }
    }

    public function getTicket($id) {
        return $this->app['db']->fetchAssoc('SELECT t.*, i.name as importance, u.name as user FROM tickets AS t
                                                INNER JOIN importances AS i ON t.importance=i.id
                                                INNER JOIN users AS u ON t.user=u.id
                                              WHERE t.id=?', [$id]);
    }

    public function getResponses($id){
        return $this->app['db']->fetchAll('SELECT *, a.name as admin FROM comments AS c
                                                INNER JOIN admins AS a ON c.admin=a.id
                                                WHERE c.ticket=? ORDER BY c.date DESC', [$id]);
    }

    public function addResponse($ticketId, $description, $adminId){
        if($this->app['db']->insert('comments', ['description' => $description, 'ticket' => $ticketId, 'admin' => $adminId])) {
            return true;
        } else {
            return false;
        }
    }

    public function updateStatus($ticketId, $status){
        if($this->app['db']->update('tickets', ['status' => $status], ['id' => $ticketId])) {
            return true;
        } else {
            return false;
        }
    }

}