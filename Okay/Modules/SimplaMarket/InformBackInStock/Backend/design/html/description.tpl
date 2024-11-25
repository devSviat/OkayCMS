{$meta_title = $btr->okay_cms__found_cheaper__title|escape scope=global}

{*Название страницы*}
<div class="row">
    <div class="col-lg-12 col-md-12">
        <div class="wrap_heading">
            <div class="box_heading heading_page">
                {$btr->simpla_market_inform_back_in_stock_title|escape}
            </div>
        </div>
    </div>
</div>

<div class="alert alert--icon">
    <div class="alert__content">
        <div class="alert__title">{$btr->general_module_description}</div>
        <p>{$btr->simpla_market_inform_back_in_stock_description1}</p>
        <p>{$btr->simpla_market_inform_back_in_stock_description2}</p>
    </div>
</div>

<div class="alert alert--icon alert--info">
    <div class="alert__content">
        <div class="alert__title">{$btr->general_module_instruction}</div>
        <p>{$btr->simpla_market_inform_back_in_stock_instruction3}</p>
        <p>{$btr->simpla_market_inform_back_in_stock_instruction4}</p>
    </div>
</div>

<div class="row">
    <div class="col-xs-12">
        <div class="boxed">
            <div style="margin: 10px 0;">
                <a style="display: inline-block;vertical-align: middle;margin: 0 10px 10px 0;" href="{$rootUrl}/Okay/Modules/SimplaMarket/InformBackInStock/Backend/design/images/inform_stock_front.png">
                    <img style="max-height: 120px" src="{$rootUrl}/Okay/Modules/SimplaMarket/InformBackInStock/Backend/design/images/inform_stock_front.png">
                </a>
            </div>
            <div style="margin: 10px 0;">
                <a style="display: inline-block;vertical-align: middle;margin: 0 10px 10px 0;" href="{$rootUrl}/Okay/Modules/SimplaMarket/InformBackInStock/Backend/design/images/inform_stock_front2.png">
                    <img style="max-height: 120px" src="{$rootUrl}/Okay/Modules/SimplaMarket/InformBackInStock/Backend/design/images/inform_stock_front2.png">
                </a>
            </div>
        </div>
    </div>
</div>

<script>
    sclipboard();
</script>