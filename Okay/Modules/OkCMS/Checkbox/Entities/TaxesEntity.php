<?php


namespace Okay\Modules\OkCMS\Checkbox\Entities;


use Okay\Core\Entity\Entity;
use Okay\Modules\OkayCMS\Hotline\ExtendsEntities\ProductsEntity;
use Okay\Modules\OkCMS\Checkbox\Init\Init;

class TaxesEntity extends Entity
{
    protected static $fields = [
        'id',
        'code', //code
        'name',
    ];

    protected static $defaultOrderFields = [
        'id DESC',
    ];

    protected static $table = '__okcms__checkbox_taxes';

    public function delete($ids)
    {
        if (empty($ids)) {
            return parent::delete($ids);
        }

        $ids = (array)$ids;
        $this->deleteTaxProducts($ids);

        return parent::delete($ids);
    }

    public function getProductTaxes($productId) {
        $select = $this->queryFactory->newSelect();
        $select->cols(['tax_id'])
            ->from(Init::CHECKBOX_PRODUCTS_TAXES_TABLE)
            ->where('product_id=:productId')
            ->bindValue('productId', $productId);

        $this->db->query($select);
        return $this->db->results('tax_id');
    }

    public function getProductTaxesCodes($productId) {
        $select = $this->queryFactory->newSelect();
        $select->cols(['code'])
            ->from($this->getTable())
            ->join('left', Init::CHECKBOX_PRODUCTS_TAXES_TABLE, 'id=tax_id')
            ->where('product_id=:productId')
            ->bindValue('productId', $productId);

        $this->db->query($select);
        return $this->db->results('code');
    }

    public function addProductTax($productId, $taxId) {
        $query = $this->queryFactory->newSqlQuery();
        $query
            ->setStatement(
                "REPLACE INTO " . Init::CHECKBOX_PRODUCTS_TAXES_TABLE . " SET product_id=:productId, tax_id=:taxId"
            )
            ->bindValues([
                'productId' => $productId,
                'taxId' => $taxId
            ]);
        $this->db->query($query);
    }

    public function deleteProductTaxes($productIds) {
        $delete = $this->queryFactory->newDelete();
        $delete->from(Init::CHECKBOX_PRODUCTS_TAXES_TABLE)
            ->where('product_id IN(:productIds)')
            ->bindValue('productIds', (array)$productIds);
        $this->db->query($delete);
    }

    public function deleteTaxProducts($taxIds) {
        $delete = $this->queryFactory->newDelete();
        $delete->from(Init::CHECKBOX_PRODUCTS_TAXES_TABLE)
            ->where('tax_id IN(:taxIds)')
            ->bindValue('taxIds', (array)$taxIds);
        $this->db->query($delete);
    }

}