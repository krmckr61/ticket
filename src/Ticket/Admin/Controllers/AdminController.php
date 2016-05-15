<?php

namespace Ticket\Admin\Controllers;

use Silex\Application;
use Silex\ControllerProviderInterface;
use Ticket\Admin\Models\Admin;
use Ticket\Admin\Models\Ticket;
use Ticket\PathFinder;
use Symfony\Component\Validator\Constraints as Assert;

class AdminController extends PathFinder implements ControllerProviderInterface
{

    private $loginMainPath = '/admin/tickets';
    private $logoffMainPath = '/admin/login';

    public function connect(Application $app)
    {
        $factory = $app['controllers_factory'];
        // Routes are defined here
        $factory->get('/', 'Ticket\Admin\Controllers\AdminController::redirect');
        $factory->match('/login', 'Ticket\Admin\Controllers\AdminController::login', 'GET|POST');
        $factory->get('/logout', 'Ticket\Admin\Controllers\AdminController::logout');
        $factory->get('/tickets', 'Ticket\Admin\Controllers\AdminController::tickets');
        $factory->match('/tickets/{id}', 'Ticket\Admin\Controllers\AdminController::ticketDetail', 'GET|POST');
        $factory->get('/categories', 'Ticket\Admin\Controllers\AdminController::categories');
        $factory->match('/categories/new', 'Ticket\Admin\Controllers\AdminController::newCategory', 'GET|POST');


        return $factory;
    }

    public function ticketDetail($id)
    {
        if ($this->isLogin()) {
            $ticket = new Ticket();

            if (isset($_POST['sendResponse'])) {
                if (isset($_POST['description']) && !empty($_POST['description'])) {
                    $description = $this->app->escape($_POST['description']);
                    if ($ticket->addResponse($id, $description, $this->app['session']->get('admin')['id'])) {
                        $datas['success'] = 'Response added successfully.';
                    } else {
                        $datas['errors'][] = 'Error occurred while adding response.';
                    }
                } else {
                    $datas['errors'][] = 'Description is required field.';
                }
            } else if (isset($_POST['updateStatus'])) {
                if (isset($_POST['status']) && !empty($_POST['status'])) {
                    $status = $this->app->escape($_POST['status']);
                    if ($ticket->updateStatus($id, $status)) {
                        $datas['success'] = 'Status update successfully.';
                    } else {
                        $datas['errors'][] = 'Error occurred while updating status.';
                    }
                } else {
                    $datas['errors'][] = 'Status is required field.';
                }
            }

            $thisTicket = $ticket->getTicket($id);
            if ($thisTicket) {
                $thisTicket['cats'] = $ticket->getChooseCategories($thisTicket['id']);
                $thisTicket['responses'] = $ticket->getResponses($thisTicket['id']);
                $datas['admin'] = $this->app['session']->get('admin');
                $datas['ticket'] = $thisTicket;
                return $this->app['twig']->render('admin/ticketDetail.twig', $datas);
            } else {
                return $this->app->abort(404, "Ticket $id does not exist.");
            }
        } else {
            return $this->app->redirect($this->logoffMainPath);
        }
    }

    public function newCategory()
    {
        if ($this->isLogin()) {
            $err = [];
            if (isset($_POST['send'])) {
                if (isset($_POST['name']) && !empty($_POST['name'])) {
                    $name = $this->app->escape($_POST['name']);
                    $ticket = new Ticket();
                    if (!$ticket->haveCategory($name)) {
                        if ($ticket->addCategory($name)) {
                            $datas['success'] = 'Category added successfully.';
                        } else {
                            $err[] = 'Error occurred while adding category.';
                        }
                    } else {
                        $err[] = 'This category already have.';
                    }
                } else {
                    $err[] = 'Name is required field.';
                }
            }
            $datas['errors'] = $err;
            $datas['admin'] = $this->app['session']->get('admin');
            return $this->app['twig']->render('admin/newCategory.twig', $datas);
        } else {
            return $this->app->redirect($this->logoffMainPath);
        }
    }

    public function categories()
    {
        if ($this->isLogin()) {
            $ticket = new Ticket();
            $categories = $ticket->getCategories();
            return $this->app['twig']->render('admin/categories.twig', ['admin' => $this->app['session']->get('admin'), 'categories' => $categories]);
        } else {
            return $this->app->redirect($this->logoffMainPath);
        }
    }

    public function redirect()
    {
        if ($this->isLogin()) {
            return $this->app->redirect('/admin/tickets');
        } else {
            return $this->app->redirect('/admin/login');
        }
    }


    public function logout()
    {
        $this->app['session']->remove('admin');
        return $this->app->redirect($this->logoffMainPath);
    }

    public function login()
    {
        if (!$this->isLogin()) {
            $datas = [];
            if (isset($_POST['send'])) {
                $err = [];
                if (isset($_POST['email']) && isset($_POST['password']) && !empty($_POST['email']) && !empty($_POST['password'])) {
                    $email = $this->app->escape($_POST['email']);
                    $password = $this->app->escape($_POST['password']);

                    $isMail = $this->app['validator']->validate($email, new Assert\Email());
                    if (count($isMail) > 0) {
                        $err[] = 'Please enter a valid e-mail address.';
                    }
                    $passwordIsValid = $this->app['validator']->validate($password, new Assert\Length(['min' => 6, 'max' => 10]));
                    if (count($passwordIsValid)) {
                        $err[] = 'The length of password field must be 6-10 characters.';
                    }
                } else {
                    $err[] = 'E-mail and password is required fields.';
                }

                if (count($err) < 1) {
                    $admin = new Admin();
                    $password = $this->getHash($password);
                    $have = $admin->get($email, $password);
                    if ($have) {
                        $this->app['session']->set('admin', $have);
                        return $this->app->redirect($this->loginMainPath);
                    } else {
                        $err[] = 'Undefined e-mail or password.';
                    }
                }
                $datas['errors'] = $err;
            }
            return $this->app['twig']->render('admin/login.twig', $datas);
        } else {
            return $this->app->redirect($this->loginMainPath);
        }
    }

    public function tickets()
    {
        if ($this->isLogin()) {
            $ticket = new Ticket();
            $imps = $ticket->getImportances();
            $cats = $ticket->getCategories();
            $dates = $ticket->getDates();

            $fields = [];
            foreach ($ticket->searchFields as $key => $searchField) {
                if (isset($_GET[$searchField]) && !empty($_GET[$searchField])) {
                    $fields[$searchField] = ['value' => $_GET[$searchField]];
                }
            }

            $tickets = $ticket->getTickets($fields);
            if ($tickets) {
                foreach ($tickets as $key => $tick) {
                    $tickets[$key]['cats'] = $ticket->getChooseCategories($tick['id']);
                }
            }
            $datas = ['admin' => $this->app['session']->get('admin'), 'importances' => $imps, 'tickets' => $tickets, 'dates' => $dates, 'categories' => $cats, 'fields' => $fields];
            return $this->app['twig']->render('admin/tickets.twig', $datas);
        } else {
            return $this->app->redirect($this->logoffMainPath);
        }
    }


    private function getHash($text, $key = 'YeniŞifrelemeMetni')
    {
        return md5($key . $text);
    }

    private function isLogin()
    {
        return $this->app['session']->has('admin');
    }

}