<?php


namespace Okay\Modules\SimplaMarket\InformBackInStock\Entities;


use Okay\Core\Entity\Entity;

class InformBackInStockEntity extends Entity
{
    protected static $fields = [
        'id',
        'product_id',
        'variant_id',
        'name',
        'email',
        'lang_id'
    ];

    protected static $table = '__inform_stock';
    protected static $tableAlias = 'i_st';

    public function addRec($rec)
    {
        //если подписка на данный товар для данного и-мейла существует возвращаем ее
        $tmprec = $this->getRec($rec->variant_id, $rec->email);

        if($tmprec) {
            return false;
        }
        $id = $this->add($rec);
        return $id;
    }

    //возвращает запись по prduct_id и email
    public function getRec($variant_id, $email)
    {
        $filter['variant_id'] = $variant_id;
        $filter['email'] = $email;
        $rec = $this->find($filter);
        return $rec;
    }


    public function deleteRec($record_id)
    {
        $this->delete($record_id);
    }

    public function getProductsIds()
    {
        $select = $this->queryFactory->newSelect();
        $select->distinct()
            ->cols(['product_id'])
            ->from('__inform_stock');

        $this->db->query($select);
        return $this->db->results('product_id');
    }

    public function getRecords($variant_id)
    {
        return $this->find(['variant_id'=>$variant_id]);
    }

    public function deleteRecords($variant_id)
    {
        $delete = $this->queryFactory->newDelete();
        $delete->from('__inform_stock')->where('variant_id IN (:ids)');
        $delete->bindValue('ids', $variant_id);
        $this->db->query($delete);;

        return true;
    }
}