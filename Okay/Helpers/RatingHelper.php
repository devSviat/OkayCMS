<?php


namespace Okay\Helpers;


use Okay\Core\Entity\Entity;
use Okay\Core\Modules\Extender\ExtenderFacade;
use Okay\Core\EntityFactory;
use Okay\Entities\CommentsEntity;

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

    private $commentsEntity;

    public function __construct(EntityFactory $entityFactory)
    {
        $this->commentsEntity = $entityFactory->get(CommentsEntity::class);
    }



    /**
     * Перераховує середній бал товару з його схвалених відгуків.
     *
     * Єдине джерело правди для `aggregateRating`: доти рейтинг жив окремим
     * анонімним голосуванням, ніяк не звʼязаним із відгуками, і сторінка
     * заявляла оцінку, під якою не було жодного тексту.
     *
     * Відгуки без зірок не рахуються - вони не є оцінкою. Коли таких немає
     * зовсім, бал і лічильник обнуляються: шаблон друкує розмітку лише при
     * `rating > 0`, тож товар просто лишається без заявленої оцінки.
     *
     * @param Entity $objectEntity сутність товару або поста
     * @param int $objectId
     * @param string $objectType значення поля `type` у коментарях
     * @return float новий середній бал
     */
    public function recalculateFromReviews(Entity $objectEntity, $objectId, $objectType = 'product')
    {
        $ratings = [];
        foreach ($this->commentsEntity->noLimit()->find([
            'object_id'  => (int)$objectId,
            'type'       => $objectType,
            'approved'   => 1,
            // Відповідь адміна лежить у тій самій таблиці з тим самим
            // object_id. Оцінки в неї зараз узятись нізвідки, але покладатись
            // на це не варто: середній бал рахується з відгуків, а відповідь
            // відгуком не є.
            'has_parent' => false,
        ]) as $comment) {
            if ($comment->rating !== null && $comment->rating > 0) {
                $ratings[] = (int)$comment->rating;
            }
        }

        $votes  = count($ratings);
        $rating = $votes > 0 ? (float)(array_sum($ratings) / $votes) : 0.0;

        $objectEntity->update((int)$objectId, ['rating' => $rating, 'votes' => $votes]);

        return ExtenderFacade::execute(__METHOD__, (float)$rating, func_get_args());
    }

}
