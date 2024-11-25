{$meta_title = $btr->additional_description_field__title|escape scope=global}

{*Название страницы*}
<div class="row">
    <div class="col-lg-12 col-md-12">
        <div class="wrap_heading">
            <div class="box_heading heading_page">
                {$btr->additional_description_field__title|escape}
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-lg-12 col-md-12">
        <div class="alert alert--icon">
            <div class="alert__content">
                <div class="alert__title">{$btr->additional_description_field__info|escape}</div>
                <p>{$btr->additional_description_field__description_1}</p>
            </div>
        </div>
    </div>
</div>
<div class="col-xs-12 boxed">
    <div class="row">
        <div class="col-lg-7 mb-2" style="text-align: center">
            <a href="{$rootUrl}/Okay/Modules/SimplaMarket/AdditionalDescriptionField/Backend/design/images/description.jpg">
                <img style="max-height: 300px" src="{$rootUrl}/Okay/Modules/SimplaMarket/AdditionalDescriptionField/Backend/design/images/description.jpg">
            </a>
        </div>

        <div class="col-lg-5 mb-2">
            <div class="alert alert--icon alert--warning">
                <div class="alert__content">
                    <div class="alert__title">{$btr->additional_description_field__warning}</div>
                    <p>{$btr->additional_description_field__description_2}</p>
                </div>
            </div>
        </div>
    </div>
</div>
