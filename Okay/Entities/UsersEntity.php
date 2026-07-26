<?php


namespace Okay\Entities;


use Okay\Core\Entity\Entity;
use Okay\Core\Modules\Extender\ExtenderFacade;
use Okay\Core\Security\PasswordHasher;

class UsersEntity extends Entity
{

    /** @var PasswordHasher|null */
    private $passwordHasher;

    protected static $fields = [
        'id',
        'email',
        'password',
        'name',
        'last_name',
        'phone',
        'group_id',
        'last_ip',
        'created',
        'remind_code',
        'remind_expire',
        'preferred_delivery_id',
        'preferred_payment_method_id',
        'g.discount',
        'g.name as group_name',
    ];

    protected static $searchFields = [
        'name',
        'email',
        'last_ip',
        'last_name',
        'phone',
    ];

    protected static $defaultOrderFields = [
        'name',
    ];

    protected static $table = '__users';
    protected static $tableAlias = 'u';
    protected static $alternativeIdField = 'email';
    protected static $langTable;
    protected static $langObject;

    // осторожно, при изменении соли испортятся текущие пароли пользователей
    private $salt = '8e86a279d6e182b3c811c559e6b15484';
    
    public function find(array $filter = [])
    {
        $this->select->join('LEFT', '__groups AS g', 'u.group_id=g.id');
        return parent::find($filter);
    }
    
    public function get($id)
    {
        if (empty($id)) {
            return ExtenderFacade::execute([static::class, __FUNCTION__], false, func_get_args());
        }

        $this->select->join('LEFT', '__groups AS g', 'u.group_id=g.id');
        
        $user = parent::get($id);
        
        if (empty($user)) {
            return ExtenderFacade::execute([static::class, __FUNCTION__], false, func_get_args());
        }

        $user->discount = floor((float)$user->discount);

        return ExtenderFacade::execute([static::class, __FUNCTION__], $user, func_get_args());
    }

    public function add($user)
    {
        $user = (array)$user;
        if (isset($user['password'])) {
            $user['password'] = $this->passwordHasher()->hash($user['password']);
        }
        
        $count = $this->count(['email'=>$user['email']]);
        
        if ($count > 0) {
            return ExtenderFacade::execute([static::class, __FUNCTION__], false, func_get_args());
        }
        
        return parent::add($user);
    }

    public function update($id, $user)
    {
        $user = (array)$user;
        if (isset($user['password'])) {
            $user['password'] = $this->passwordHasher()->hash($user['password']);
        }
        
        return parent::update($id, $user);
    }

    public function delete($ids)
    {
        if (!empty($ids)) {
            $update = $this->queryFactory->newUpdate();
            $update->table('__orders')
                ->set('user_id', 0)
                ->where('user_id IN (:user_id)')
                ->bindValue('user_id', $ids);
            
            $this->db->query($update);
            
        }

        return parent::delete($ids);
    }

    /**
     * @param string $email
     * @param string $password
     * @return int|false
     */
    public function checkPassword($email, $password)
    {
        // Пароль більше не порівнюється в SQL: у сучасних хешів своя сіль
        // у кожному рядку, тому знаходимо користувача за email і перевіряємо
        // хеш у PHP.
        $user = $this->cols(['id', 'password'])->findOne([
            'email' => $email,
            'limit' => 1,
        ]);

        if (empty($user) || !$this->passwordHasher()->verify($password, $user->password, $this->salt)) {
            return ExtenderFacade::execute([static::class, __FUNCTION__], false, func_get_args());
        }

        $userId = (int)$user->id;

        if ($this->passwordHasher()->needsRehash($user->password)) {
            $this->updatePasswordHash($userId, $this->passwordHasher()->hash($password));
        }

        return ExtenderFacade::execute([static::class, __FUNCTION__], $userId, func_get_args());
    }

    /**
     * Записує готовий хеш, оминаючи повторне хешування в update().
     *
     * @param int $userId
     * @param string $hash
     * @return void
     */
    public function updatePasswordHash($userId, $hash)
    {
        parent::update((int)$userId, ['password' => $hash]);
    }

    private function passwordHasher()
    {
        if ($this->passwordHasher === null) {
            $this->passwordHasher = new PasswordHasher();
        }

        return $this->passwordHasher;
    }

    public function generatePass($passLen = 6) {
        $pass = '';
        for ($i=0; $i< $passLen; $i++) {
            $ranges = [
                rand(48, 57),
                rand(65, 90),
                rand(97, 122),
            ];
            $pass .= chr($ranges[rand(0, 2)]);
        }

        return ExtenderFacade::execute([static::class, __FUNCTION__], $pass, func_get_args());
    }

    protected function customOrder($order = null, array $orderFields = [], array $additionalData = [])
    {
        switch ($order) {
            case 'date':
                $orderFields = ['u.created DESC'];
                break;
            case 'cnt_order':
                $orderFields = ["(select count(o.id) from __orders o where o.user_id = u.id) DESC"];
                break;
        }

        return ExtenderFacade::execute([static::class, __FUNCTION__], $orderFields, func_get_args());
    }
}
