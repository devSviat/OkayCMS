<?php


namespace Okay\Controllers;


use Okay\Core\Notify;
use Okay\Core\Response;
use Okay\Core\Security\RecoveryToken;
use Okay\Core\Security\SessionNames;
use Okay\Entities\OrdersEntity;
use Okay\Entities\OrderStatusEntity;
use Okay\Entities\UsersEntity;
use Okay\Core\Router;
use Okay\Helpers\CommentsHelper;
use Okay\Helpers\OrdersHelper;
use Okay\Helpers\UserHelper;
use Okay\Helpers\ValidateHelper;
use Okay\Requests\UserRequest;

class UserController extends AbstractController
{

    /** Підтверджений стан скидання пароля, живе між редиректом і формою */
    const RECOVERY_SESSION_KEY = 'password_recovery_state';


    public function render(
        UsersEntity $usersEntity,
        ValidateHelper $validateHelper,
        UserRequest $userRequest,
        OrdersEntity $ordersEntity,
        OrderStatusEntity $orderStatusEntity,
        UserHelper $userHelper,
        CommentsHelper $commentsHelper,
        OrdersHelper $ordersHelper
    ) {
        if (empty($this->user->id)) {
            $this->response->redirectTo(Router::generateUrl('login', [], true));
        }

        if ($user = $userRequest->postProfileUser()) {
            /*Валидация данных*/
            if ($error = $validateHelper->getUserError($user, $this->user->id)) {
                $this->design->assign('error', $error);
            } elseif($usersEntity->update($this->user->id, $user)) {
                $this->user = $usersEntity->get((int)$this->user->id);
                $this->design->assign('user', $this->user);
                $this->design->assign('user_updated', true, true);
            } else {
                $this->design->assign('error', 'unknown error');
            }

            if ($password = $this->request->post('password')) {
                $usersEntity->update($this->user->id, ['password'=>$password]);
            }
        }

        /*Выборка истории заказов клиента*/
        $orders = $ordersEntity->mappedBy('id')->find(['user_id'=>$this->user->id]);
        
        foreach ($orders as $order) {
            $order->purchases = $ordersHelper->getOrderPurchasesList(intval($order->id));

            // Скидки
            $order->discounts = $ordersHelper->getDiscounts($order->id);
        }

        $allStatuses = $orderStatusEntity->mappedBy('id')->find();

        $paymentMethods = $userHelper->getPaymentMethodsListForUser();
        $deliveries = $userHelper->getDeliveriesListForUser($paymentMethods);
        $this->design->assign('payment_methods', $paymentMethods);
        $this->design->assign('deliveries', $deliveries);
        
        if (!empty($this->user->preferred_payment_method_id) && isset($paymentMethods[$this->user->preferred_payment_method_id])) {
            $this->design->assign('active_payment', $paymentMethods[$this->user->preferred_payment_method_id]);
        }
        
        if (!empty($this->user->preferred_delivery_id) && isset($deliveries[$this->user->preferred_delivery_id])) {
            $activeDelivery = $deliveries[$this->user->preferred_delivery_id];
        } else {
            $activeDelivery = reset($deliveries);
        }
        $this->design->assign('active_delivery', $activeDelivery);

        $userComments = $commentsHelper->getList(['user_id' => $this->user->id]);
        $userComments = $commentsHelper->attachTargetEntitiesToComments($userComments);
        $userComments = $commentsHelper->attachAnswers($userComments);
        $this->design->assign('user_comments', $userComments);
        
        $this->design->assign('orders_status', $allStatuses);
        $this->design->assign('orders', $orders);

        $activeTab = null;
        switch (Router::getCurrentRouteName()) {
            case 'user_orders':
                $activeTab = 'orders';
                break;
            case 'user_comments':
                $activeTab = 'comments';
                break;
            case 'user_favorites':
                $activeTab = 'favorites';
                break;
            case 'user_browsed':
                $activeTab = 'browsed';
                break;
            default:
                $activeTab = $userHelper->defaultActiveTab(Router::getCurrentRouteName());
                break;
        }
        
        $this->design->assign('active_tab', $activeTab);
        $this->design->assign('meta_title', $this->user->name);
        
        $this->design->assign('noindex_follow', true);
        $this->design->assign('canonical', Router::generateUrl('user', [], true));
        
        $this->response->setContent('user.tpl');
    }
    
    public function register(UserHelper $userHelper, UserRequest $userRequest, ValidateHelper $validateHelper)
    {
        if (!empty($this->user->id)) {
            $this->response->redirectTo(Router::generateUrl('user', [], true));
        }

        if ($this->request->method('post')) {
            // Реєстрація мутує стан, тож охороняється як решта форм вітрини.
            $this->requireSameOrigin();
            $this->requireCustomerCsrf();
        }

        if ($this->request->method('post') && ($user = $userRequest->postRegisterUser())) {
            /*Валидация данных клиента*/
            if ($error = $validateHelper->getUserRegisterError($user)) {
                $this->design->assign('error', $error);
            } elseif ($userId = $userHelper->register($user)) {
                $this->response->redirectTo(Router::generateUrl('user', [], true));
            } else {
                $this->design->assign('error', 'unknown error');
            }
        }

        $this->design->assign('noindex_follow', true);
        $this->design->assign('canonical', Router::generateUrl('register', [], true));
        
        $this->response->setContent('register.tpl');
    }

    public function login(UserHelper $userHelper, ValidateHelper $validateHelper)
    {
        if (!empty($this->user->id)) {
            $this->response->redirectTo(Router::generateUrl('user', [], true));
        }

        if ($this->request->method('post')) {
            // Без цього чужа сторінка входить у свій акаунт у браузері жертви,
            // і подальші її замовлення лягають в чужу історію.
            $this->requireSameOrigin();
            $this->requireCustomerCsrf();

            $email    = $this->request->post('email');
            $password = $this->request->post('password');
            $this->design->assign('email', $email);

            if ($error = $validateHelper->getUserLoginError($email, $password)) {
                $this->design->assign('error', $error);
            } elseif ($userId = $userHelper->login($email, $password)) {
                $this->response->redirectTo(Router::generateUrl('user', [], true));
            } else {
                $this->design->assign('error', 'unknown error');
            }
        }

        $this->design->assign('noindex_follow', true);
        $this->design->assign('canonical', Router::generateUrl('login', [], true));
        
        $this->response->setContent('login.tpl');
    }
    
    public function logout(UserHelper $userHelper)
    {
        $userHelper->logout();
        $this->response->redirectTo(Router::generateUrl('main', [], true));
        return;
    }
    
    public function passwordRemind(UsersEntity $usersEntity, Notify $notify, UserHelper $userHelper, RecoveryToken $recoveryToken, $code = '')
    {
        // Перехід за посиланням відновлення більше не авторизує користувача.
        // Він лише підтверджує токен і відкриває форму нового пароля.
        if (!empty($code)) {
            if ($recoveryToken->isValidFormat($code)) {
                $user = $usersEntity->findOne([
                    'remind_code' => $recoveryToken->digest($code),
                    'limit'       => 1,
                ]);

                if (!empty($user) && date('Y-m-d H:i:s') <= $user->remind_expire) {
                    $_SESSION[self::RECOVERY_SESSION_KEY] = [
                        'user_id' => (int)$user->id,
                        'digest'  => $user->remind_code,
                        'expires' => $user->remind_expire,
                    ];

                    // Прибираємо токен з адресного рядка, щоб він не витік
                    // через Referer або історію браузера.
                    $this->response->redirectTo(Router::generateUrl('password_remind', [], true));
                }
            }

            $this->design->assign('recovery_expired', true);
            $this->design->assign('noindex_follow', true);
            $this->design->assign('canonical', Router::generateUrl('password_remind', [], true));
            $this->response->setContent('password_remind.tpl');
            return;
        }

        $state = isset($_SESSION[self::RECOVERY_SESSION_KEY]) ? $_SESSION[self::RECOVERY_SESSION_KEY] : null;

        // Встановлення нового пароля за підтвердженим токеном
        if (!empty($state) && $this->request->method('post') && $this->request->post('reset_password')) {
            $newPassword = (string)$this->request->post('new_password');
            $newPasswordCheck = (string)$this->request->post('new_password_check');

            $user = $usersEntity->get((int)$state['user_id']);

            if (empty($user) || $user->remind_code !== $state['digest'] || date('Y-m-d H:i:s') > $state['expires']) {
                unset($_SESSION[self::RECOVERY_SESSION_KEY]);
                $this->design->assign('recovery_expired', true);
            } elseif (trim($newPassword) === '') {
                $this->design->assign('recovery_mode', true);
                $this->design->assign('error', 'password_empty');
            } elseif ($newPassword !== $newPasswordCheck) {
                $this->design->assign('recovery_mode', true);
                $this->design->assign('error', 'password_wrong');
            } else {
                // Токен гаситься до підвищення привілеїв, тому повторний
                // перехід за тим самим посиланням уже нічого не дає.
                $usersEntity->update((int)$user->id, ['remind_code' => null, 'remind_expire' => null]);
                $usersEntity->update((int)$user->id, ['password' => $newPassword]);
                unset($_SESSION[self::RECOVERY_SESSION_KEY]);

                SessionNames::regenerate();
                $_SESSION['user_id'] = (int)$user->id;

                $userHelper->mergeCart();
                $userHelper->mergeWishlist();
                $userHelper->mergeComparison();
                $userHelper->mergeBrowsedProducts();

                $this->response->redirectTo(Router::generateUrl('user', [], true));
            }
        } elseif (!empty($state)) {
            $this->design->assign('recovery_mode', true);
        }

        // Якщо запостили email
        if ($this->request->method('post') && $this->request->post('email')) {
            $email = $this->request->post('email');

            $user = $usersEntity->get($email);
            if (!empty($user->id)) {
                $token = $recoveryToken->create();

                // У базі лежить лише digest: сам токен є тільки в листі.
                $usersEntity->update($user->id, [
                    'remind_code'   => $recoveryToken->digest($token),
                    'remind_expire' => $recoveryToken->expiresAt(),
                ]);

                $notify->emailPasswordRemind($user->id, $token);
            }

            // Відповідь однакова для наявної й неіснуючої адреси,
            // інакше форма працює як оракул наявності акаунта.
            $this->design->assign('email_sent', true);
        }

        $this->design->assign('noindex_follow', true);
        $this->design->assign('canonical', Router::generateUrl('password_remind', [], true));

        $this->response->setContent('password_remind.tpl');
    }
 
    public function wellKnownChangePassword()
    {
        Response::redirectTo(Router::generateUrl('user', [], true), 302);
    }
    
}
