<?php
namespace Ticket\User\Controllers;

use Silex\Application;
use Silex\ControllerProviderInterface;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Ticket\User\Models\Ticket;
use Ticket\User\Models\User;
use Ticket\PathFinder;
use Symfony\Component\Validator\Constraints as Assert;

class UserController extends PathFinder implements ControllerProviderInterface
{

    private $loginMainPath = '/user/tickets';
    private $logoffMainPath = '/user/login';

    public function connect(Application $app)
    {
        $factory = $app['controllers_factory'];
        // Routes are defined here
        $factory->get('/', 'Ticket\User\Controllers\UserController::redirect');
        $factory->match('/user/login', 'Ticket\User\Controllers\UserController::login', 'GET|POST');
        $factory->get('/user/logout', 'Ticket\User\Controllers\UserController::logout');
        $factory->get('/user/tickets', 'Ticket\User\Controllers\UserController::tickets');
        $factory->match('/user/tickets/new', 'Ticket\User\Controllers\UserController::newTicket', 'GET|POST');
        $factory->match('/user/tickets/comments/{id}', 'Ticket\User\Controllers\UserController::ticketDetails', 'GET|POST');

        return $factory;
    }

    public function ticketDetails($id){
        if ($this->isLogin()) {
            $ticket = new Ticket();
            $thisTicket = $ticket->getTicketByUser($this->app['session']->get('user')['id'], $id);
            if($thisTicket) {
                $thisTicket['responses'] = $ticket->getResponses($id);
                $thisTicket['categories'] = $ticket->getChooseCategories($id);
                return $this->app['twig']->render('user/ticketDetail.twig', ['ticket' => $thisTicket, 'user' => $this->app['session']->get('user')]);
            } else {
                return $this->app->abort(404, "Ticket $id does not exist.");
            }
        } else {
            return $this->app->redirect($this->logoffMainPath);
        }
    }

    public function redirect()
    {
        if ($this->isLogin()) {
            return $this->app->redirect($this->loginMainPath);
        } else {
            return $this->app->redirect($this->logoffMainPath);
        }
    }

    public function newTicket()
    {
        if ($this->isLogin()) {
            $ticket = new Ticket();
            $cats = $ticket->getCategories();
            $imps = $ticket->getImportances();
            $datas = ['user' => $this->app['session']->get('user'), 'categories' => $cats, 'importances' => $imps];

            if (isset($_POST['send'])) {
                $err = [];
                if (isset($_POST['head']) && !empty($_POST['head']) && isset($_POST['description']) && !empty($_POST['description']) && isset($_POST['importance']) && !empty($_POST['importance']) && isset($_POST['categories']) && count($_POST['categories']) > 0 && isset($_FILES['file']['name']) && $_FILES['file']['error'] == 0) {
                    $head = $this->app->escape($_POST['head']);
                    $description = $this->app->escape($_POST['description']);
                    $importance = $this->app->escape($_POST['importance']);
                    $categories = $_POST['categories'];
                    $headIsValid = $this->app['validator']->validate($head, new Assert\Length(['max' => 255]));
                    if (!$headIsValid) {
                        $err[] = 'Head field is too long.';
                    }
                    foreach ($categories as $category) {
                        if (!is_numeric($category)) {
                            $err[] = 'Category field is invalid.';
                            break;
                        }
                    }
                } else {
                    $err[] = 'All fields are required.';
                }
                if (count($err) < 1) {
                    $upload = $this->uploadFile($_FILES['file']);
                    if ($upload) {
                        if ($ticket->add(['user' => $this->app['session']->get('user')['id'], 'head' => $head, 'description' => $description, 'importance' => $importance, 'categories' => $categories, 'file' => $upload])) {
                            $datas['success'] = 'Ticket successfully added.';
                        } else {
                            $err[] = 'Ticket could not be added.';
                        }
                    }
                }
                $datas['errors'] = $err;
            }
            return $this->app['twig']->render('user/newTicket.twig', $datas);
        } else {
            return $this->app->redirect($this->logoffMainPath);
        }
    }

    private function uploadFile($file)
    {
        $path = "files/";
        $uploadFileName = md5(basename($file['name']) . time()) . '.' . pathinfo($file['name'], PATHINFO_EXTENSION);
        $uploadFilePath = $path . $uploadFileName;
        if (!move_uploaded_file($file['tmp_name'], $uploadFilePath)) {
            return false;
        }
        return $uploadFileName;

    }

    public function tickets()
    {
        if ($this->isLogin()) {
            $ticket = new Ticket();
            $datas = $ticket->getTicketsByUser($this->app['session']->get('user')['id']);
            if ($datas) {
                foreach ($datas as $key => $data) {
                    $datas[$key]['cats'] = $ticket->getChooseCategories($data['id']);
                }
            }
            return $this->app['twig']->render('user/tickets.twig', ['user' => $this->app['session']->get('user'), 'tickets' => $datas]);
        } else {
            return $this->app->redirect($this->logoffMainPath);
        }
    }


    public function logout()
    {
        $this->app['session']->remove('user');
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
                    $user = new User;
                    $password = $this->getHash($password);
                    $have = $user->get($email, $password);
                    if ($have) {
                        $this->app['session']->set('user', $have);
                        return $this->app->redirect($this->loginMainPath);
                    } else {
                        $err[] = 'Undefined e-mail or password.';
                    }
                }
                $datas['errors'] = $err;
            }
            return $this->app['twig']->render('user/login.twig', $datas);
        } else {
            return $this->app->redirect($this->loginMainPath);
        }
    }

    private function getHash($text, $key = 'BizeHerYerTrabzon')
    {
        return md5($key . $text);
    }

    private function isLogin()
    {
        return $this->app['session']->has('user');
    }

}