<?php


namespace Okay\Helpers;


use Okay\Core\Design;
use Okay\Core\EntityFactory;
use Okay\Core\Languages;
use Okay\Core\Modules\Extender\ExtenderFacade;
use Okay\Core\Notify;
use Okay\Core\Request;
use Okay\Core\Response;
use Okay\Core\Security\FormToken;
use Okay\Core\Security\SafeRedirect;
use Okay\Core\Security\StorefrontGuard;
use Okay\Entities\BlogEntity;
use Okay\Entities\CommentsEntity;
use Okay\Entities\ProductsEntity;
use Okay\Requests\CommonRequest;
use Psr\Log\LoggerInterface;

class CommentsHelper implements GetListInterface
{
    /** Форма коментаря для FormToken. */
    const COMMENT_FORM = 'comment';

    private $entityFactory;
    private $commentsRequest;
    private $validateHelper;
    private $design;
    private $notify;
    private $languages;
    private $user;
    private $mainHelper;
    private $storefrontGuard;
    private $request;
    private $logger;

    public function __construct(
        EntityFactory   $entityFactory,
        CommonRequest   $commentsRequest,
        ValidateHelper  $validateHelper,
        Design          $design,
        Notify          $notify,
        MainHelper      $mainHelper,
        Languages       $languages,
        StorefrontGuard $storefrontGuard,
        Request         $request,
        LoggerInterface $logger
    ) {
        $this->logger = $logger;
        $this->entityFactory = $entityFactory;
        $this->commentsRequest = $commentsRequest;
        $this->validateHelper = $validateHelper;
        $this->design = $design;
        $this->notify = $notify;
        $this->languages = $languages;
        $this->mainHelper = $mainHelper;
        $this->storefrontGuard = $storefrontGuard;
        $this->request = $request;
        $this->user = $mainHelper->getCurrentUser();
    }

    /**
     * Метод возвращает комментарии для товаров или записей блога
     *
     * Данный метод остаётся для обратной совместимости, но объявлен как deprecated, и будет удалён в будущих версиях
     * 
     * @param string $objectType
     * @param int $objectId
     * @return array
     * @throws \Exception
     */
    public function getCommentsList($objectType, $objectId)
    {
        trigger_error('Method ' . __METHOD__ . ' is deprecated. Please use getList', E_USER_DEPRECATED);
        $filter = $this->getCommentsFilter($objectType, $objectId);
        $sortName = $this->getCurrentSort();
        $comments = $this->getList($filter, $sortName);
        $comments = $this->attachAnswers($comments);

        return ExtenderFacade::execute(__METHOD__, $comments, func_get_args());
    }

    /**
     * @param string $objectType
     * @param int $objectId
     * @return array
     */
    public function getCommentsFilter($objectType, $objectId)
    {
        $filter = [
            'has_parent' => false,
            'type' => $objectType,
            'object_id' => $objectId,
            'approved' => 1,
            'ip' => $_SERVER['REMOTE_ADDR']
        ];

        return ExtenderFacade::execute(__METHOD__, $filter, func_get_args());
    }

    /**
     * @return mixed
     */
    public function getCurrentSort()
    {
        return ExtenderFacade::execute(__METHOD__, null, func_get_args());
    }

    /**
     * @param array $filter
     * @param string $sortName
     * @param array $excludedFields
     * @return array
     * @throws \Exception
     */
    public function getList($filter = [], $sortName = null, $excludedFields = null)
    {
        if ($excludedFields === null) {
            $excludedFields = $this->getExcludeFields();
        }

        /** @var CommentsEntity $commentsEntity */
        $commentsEntity = $this->entityFactory->get(CommentsEntity::class);

        // Исключаем колонки, которые нам не нужны
        if (is_array($excludedFields) && !empty($excludedFields)) {
            $commentsEntity->cols(CommentsEntity::getDifferentFields($excludedFields));
        }

        $commentsEntity->order($sortName, $this->getOrderCommentsAdditionalData());
        $comments = $commentsEntity->mappedBy('id')->find($filter);

        return ExtenderFacade::execute(__METHOD__, $comments, func_get_args());
    }

    /**
     * @return array
     */
    public function getExcludeFields()
    {
        $excludedFields = [];
        return ExtenderFacade::execute(__METHOD__, $excludedFields, func_get_args());
    }

    /**
     * @return array
     */
    private function getOrderCommentsAdditionalData()
    {
        $orderAdditionalData = [];
        return ExtenderFacade::execute(__METHOD__, $orderAdditionalData, func_get_args());
    }

    /**
     * @param array $comments
     * @return array mixed
     */
    public function attachAnswers($comments)
    {
        if (!empty($comments)) {
            /** @var CommentsEntity $commentsEntity */
            $commentsEntity = $this->entityFactory->get(CommentsEntity::class);

            $filter = [
                'has_parent' => true,
                'approved' => 1,
                'ip' => $_SERVER['REMOTE_ADDR'],
            ];
            foreach ($comments as $comment) {
                $filter['composite_object_type_id'][$comment->type][] = $comment->object_id;
            }
            $answers = $commentsEntity->mappedBy('id')->order('id DESC')->find($filter);
            foreach ($answers as $answer) {
                if (isset($answers[$answer->parent_id])) {
                    $answers[$answer->parent_id]->children[$answer->id] = $answer;
                } else if (isset($comments[$answer->parent_id])) {
                    $comments[$answer->parent_id]->children[$answer->id] = $answer;
                }
            }
        }

        return ExtenderFacade::execute(__METHOD__, $comments, func_get_args());
    }

    /**
     * @param string $objectType
     * @param int $objectId
     * @throws \Exception
     */
    public function addCommentProcedure($objectType, $objectId)
    {
        if (($comment = $this->commentsRequest->postComment()) !== null) {
            $this->storefrontGuard->requireCustomerCsrf();

            if ($error = $this->validateHelper->getCommentValidateError($comment)) {
                $this->design->assign('error', $error);
            } else {

                // Редирект нижче рятує лише браузерний F5. Від подвійного кліку
                // й повтору запиту рятує токен: без нього два майже одночасні
                // POST давали два коментарі й два листи.
                if (!$this->acceptComment($comment, $objectType, $objectId)) {
                    Response::redirectTo($this->backUrl(''));
                }

                /** @var CommentsEntity $commentsEntity */
                $commentsEntity = $this->entityFactory->get(CommentsEntity::class);

                // Создаем комментарий
                $comment->object_id = $objectId;
                $comment->type      = $objectType;
                $comment->ip        = $_SERVER['REMOTE_ADDR'];
                $comment->lang_id   = $this->languages->getLangId();

                if (!empty($this->user->id)) {
                    $comment->user_id = $this->user->id;
                } elseif (!empty($user = $this->mainHelper->getCurrentUser()) && !empty($user->id)) {
                    $comment->user_id = $user->id;
                }

                // Добавляем комментарий в базу
                $commentId = $commentsEntity->add($comment);

                // Порожній id - запис не пройшов, а редирект нижче вів би на
                // #comment_ без id, тобто показував успіх.
                if (empty($commentId)) {
                    $this->logger->error("Коментар до {$objectType} #{$objectId} не збережено");
                    $this->design->assign('error', 'not_saved');
                    return;
                }

                // Отправляем email
                $this->notify->emailCommentAdmin($commentId);

                ExtenderFacade::execute(__METHOD__, $commentId, func_get_args());

                Response::redirectTo($this->backUrl('#comment_' . $commentId));
            }
        }
    }

    /**
     * Ціль обов'язково входить у відбиток: без неї однаковий короткий відгук
     * («Дуже задоволений») на двох різних товарах дає той самий хеш, і другий
     * зникає як уявний повтор.
     */
    private function acceptComment($comment, $objectType, $objectId)
    {
        return FormToken::accept(
            self::COMMENT_FORM,
            $this->request->post('form_token'),
            [$comment, $objectType, $objectId],
            FormToken::ACCIDENT_TTL
        );
    }

    /**
     * REQUEST_URI приходить із рядка запиту: "GET //evil.com" дає
     * protocol-relative редирект на чужий хост.
     */
    private function backUrl($anchor)
    {
        $backUrl = $_SERVER['REQUEST_URI'] . $anchor;

        if (!SafeRedirect::isSameOrigin($backUrl, Request::getDomainWithProtocol())) {
            return Request::getRootUrl();
        }

        return $backUrl;
    }

    public function attachTargetEntitiesToComments($comments)
    {

        /** @var ProductsEntity $productsEntity */
        $productsEntity = $this->entityFactory->get(ProductsEntity::class);

        /** @var BlogEntity $blogEntity */
        $blogEntity = $this->entityFactory->get(BlogEntity::class);
        
        $productsIds = [];
        $postsIds    = [];
        foreach ($comments as $comment) {
            if ($comment->type == 'product') {
                $productsIds[] = $comment->object_id;
            }
            if ($comment->type == 'post') {
                $postsIds[] = $comment->object_id;
            }
        }

        $products = [];
        if (!empty($productsIds)) {
            foreach ($productsEntity->find(['id' => $productsIds, 'limit' => count($productsIds)]) as $p) {
                $products[$p->id] = $p;
            }
        }

        $posts = [];
        if (!empty($postsIds)) {
            foreach ($blogEntity->find(['id' => $postsIds]) as $p) {
                $posts[$p->id] = $p;
            }
        }

        foreach ($comments as $comment) {
            if ($comment->type == 'product' && isset($products[$comment->object_id])) {
                $comment->product = $products[$comment->object_id];
            }
            if ($comment->type == 'post' && isset($posts[$comment->object_id])) {
                $comment->post = $posts[$comment->object_id];
            }
        }

        return ExtenderFacade::execute(__METHOD__, $comments, func_get_args());
    }
    
}