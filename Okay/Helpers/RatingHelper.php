<?php


namespace Okay\Helpers;


use Okay\Core\Entity\Entity;
use Okay\Core\Modules\Extender\ExtenderFacade;
use Okay\Core\Request;

/**
 * Голос за товар або пост.
 *
 * Спільна для ProductController і BlogController: та сама логіка була в обох
 * копіями, тож полагоджена в одному місці лишалась би дірявою в другому.
 */
class RatingHelper
{
    /** Шкала віджета зірок у темах. */
    public const MIN_RATING = 1;
    public const MAX_RATING = 5;

    /** Уже проголосував. */
    public const ALREADY_VOTED = 0;

    /** Голос не прийнято. */
    public const REJECTED = -1;

    private $request;

    public function __construct(Request $request)
    {
        $this->request = $request;
    }

    /**
     * @param Entity $entity сутність із полями rating і votes
     * @param string $idPrefix префікс, який тема кладе в id блока
     * @param string $sessionKey де тримати перелік уже оцінених обʼєктів
     * @return float новий середній бал, ALREADY_VOTED або REJECTED
     */
    public function vote(Entity $entity, $idPrefix, $sessionKey)
    {
        $objectId = (int)str_replace($idPrefix, '', (string)$this->request->post('id'));
        $rating   = $this->normalizeRating($this->request->post('rating'));

        if ($objectId <= 0 || $rating === null) {
            return ExtenderFacade::execute(__METHOD__, (float)self::REJECTED, func_get_args());
        }

        if (!isset($_SESSION[$sessionKey])) {
            $_SESSION[$sessionKey] = [];
        }

        if (in_array($objectId, $_SESSION[$sessionKey])) {
            return ExtenderFacade::execute(__METHOD__, (float)self::ALREADY_VOTED, func_get_args());
        }

        $object = $entity->cols(['rating', 'votes'])->get($objectId);
        if (empty($object)) {
            return ExtenderFacade::execute(__METHOD__, (float)self::REJECTED, func_get_args());
        }

        $rate = ($object->rating * $object->votes + $rating) / ($object->votes + 1);

        $entity->update($objectId, ['rating' => $rate, 'votes' => $object->votes + 1]);
        $_SESSION[$sessionKey][] = $objectId;

        return ExtenderFacade::execute(__METHOD__, (float)$rate, func_get_args());
    }

    /**
     * Поза шкалою голос відхиляється, а не притискається до межі: з `999999`
     * вийшла б пʼятірка, якої ніхто не ставив. Бал іде в `aggregateRating` на
     * сторінці товару, тож будь-яке додумування тут - це вигадана цифра в
     * структурованих даних для пошуковика.
     *
     * @param mixed $posted
     * @return float|null null означає, що значення не приймається
     */
    private function normalizeRating($posted)
    {
        if (!is_numeric($posted)) {
            return null;
        }

        $rating = (float)$posted;

        return $rating >= self::MIN_RATING && $rating <= self::MAX_RATING ? $rating : null;
    }
}
