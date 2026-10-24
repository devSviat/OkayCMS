
<script>
    {if $hintId == "hint_categories"}
        function learningCategories(){
            var intro = introJs();
            intro.setOptions({
                exitOnOverlayClick: false,
                showStepNumbers: false,
                showBullets: false,
                showProgress: true,
                prevLabel: '« {$btr->learning_hint_prev|escape:'javascript'}',
                nextLabel: '{$btr->learning_hint_next|escape:'javascript'} »',
                skipLabel: '×',
                doneLabel: '{$btr->learning_hint_next_done|escape:'javascript'}',
                steps: [
                    {
                        intro: '{$btr->learning_hint_categories_intro|escape:'javascript'}',
                    },
                    {
                        intro: '{$btr->learning_hint_categories_intro2|escape:'javascript'}',
                        position: 'left',
                    },
                    {
                        element: '.fn_step-1:first-child',
                        intro: '{$btr->learning_hint_categories_item|escape:'javascript'}',
                        position: 'top',
                    },
                    {
                        element: '.fn_step-1:first-child .okay_list_subicon',
                        intro: '{$btr->learning_hint_categories_subicon|escape:'javascript'}',
                        position: 'top',
                    },
                    {
                        element: '.fn_step-1:first-child .move_zone',
                        intro: '{$btr->learning_hint_categories_move_zone|escape:'javascript'}',
                        position: 'top',
                    },
                    {
                        element: '.fn_step-1:first-child .okay_list_check',
                        intro: '{$btr->learning_hint_categories_check|escape:'javascript'}',
                        position: 'top',
                    },
                    {
                        element: '.fn_step-1:first-child .okay_list_photo',
                        intro: '{$btr->learning_hint_categories_photo|escape:'javascript'}',
                        position: 'top',
                    },
                    {
                        element: '.fn_step-1:first-child .okay_list_categories_name',
                        intro: '{$btr->learning_hint_categories_name|escape:'javascript'}',
                        position: 'top',
                    },
                    {
                        element: '.fn_step-1:first-child .okay_list_status',
                        intro: '{$btr->learning_hint_categories_status|escape:'javascript'}',
                        position: 'left',
                    },
                    {
                        element: '.fn_step-1:first-child .okay_list_setting a',
                        intro: '{$btr->learning_hint_categories_setting|escape:'javascript'}',
                        position: 'left',
                    },
                    {
                        element: '.fn_step-1:first-child .okay_list_setting button',
                        intro: '{$btr->learning_hint_categories_setting_duplicate|escape:'javascript'}',
                        position: 'left',
                    },
                    {
                        intro: '{$btr->learning_hint_categories_add|escape:'javascript'}',

                    }

                ]
            });
            intro.start();
            intro.oncomplete(function() {
                window.location.href = 'index.php?controller=CategoryAdmin&multipage=&id=1';
            });
        }
        if (RegExp('multipage', 'gi').test(window.location.search)) {
            learningCategories();
        }
    {/if}

    {if $hintId == "hint_category"}
        function learningCategory(){
            var intro = introJs();
            intro.setOptions({
            exitOnOverlayClick: false,
            showStepNumbers: false,
            showBullets: false,
            showProgress: true,
            prevLabel: '« {$btr->learning_hint_prev|escape:'javascript'}',
            nextLabel: '{$btr->learning_hint_next|escape:'javascript'} »',
            skipLabel: '×',
            doneLabel: '{$btr->learning_hint_done|escape:'javascript'}',
            steps: [
            {
            intro: '{$btr->learning_hint_category_intro|escape:'javascript'}',
            },
            {
            element: '.fn_step-1',
            intro: '{$btr->learning_hint_category_name|escape:'javascript'}',
            position: 'bottom',
            },
            {
            element: '.fn_step-2',
            intro: '{$btr->learning_hint_category_url|escape:'javascript'}',
            position: 'bottom',
            },
            {
            element: '.fn_step-3',
            intro: '{$btr->learning_hint_category_activity|escape:'javascript'}',
            position: 'left',
            },
            {
            element: '.fn_step-4',
            intro: '{$btr->learning_hint_category_images|escape:'javascript'}',
            },
            {
            element: '.fn_step-5',
            intro: '{$btr->learning_hint_category_h1|escape:'javascript'}',
            },
            {
            element: '.fn_step-6',
            intro: '{$btr->learning_hint_category_tree|escape:'javascript'}',
            position: 'left',
            },
            {
            element: '.fn_step-7',
            intro: '{$btr->learning_hint_meta_data|escape:'javascript'}',
            },
            {
            element: '.fn_step-8',
            intro: '{$btr->learning_hint_category_descriptions|escape:'javascript'}',
            },
            {
            intro: '{$btr->learning_hint_category_finish|escape:'javascript'}',
            position: 'bottom',
            }
            ]
            });
            intro.start();
            intro.oncomplete(function() {
                $.ajax({
                    url: 'ajax/lesson_done.php',
                    data: {
                        lesson: 1
                    }
                })
                .done(function() {
                    window.location.href = 'index.php?controller=LearningAdmin';
                });
            });
        }
        if (RegExp('multipage', 'gi').test(window.location.search)) {
            learningCategory();
        }
    {/if}

    {if $hintId == "hint_brands"}
        function learningBrands(){
            var intro = introJs();
            intro.setOptions({
                exitOnOverlayClick: false,
                showStepNumbers: false,
                showBullets: false,
                showProgress: true,
                prevLabel: '« {$btr->learning_hint_prev|escape:'javascript'}',
                nextLabel: '{$btr->learning_hint_next|escape:'javascript'} »',
                skipLabel: '×',
                doneLabel: '{$btr->learning_hint_next_done|escape:'javascript'}',
                steps: [
                    {
                        intro: '{$btr->learning_hint_brands_intro|escape:'javascript'}',
                    },
                    {
                        intro: '{$btr->learning_hint_brands_intro2|escape:'javascript'}',
                        position: 'left',
                    },
                    {
                        element: '.fn_step-1:first-child',
                        intro: '{$btr->learning_hint_brands_item|escape:'javascript'}',
                        position: 'top',
                    },
                    {
                        element: '.fn_step-1:first-child .move_zone',
                        intro: '{$btr->learning_hint_brands_move_zone|escape:'javascript'}',
                        position: 'top',
                    },
                    {
                        element: '.fn_step-1:first-child .okay_list_check',
                        intro: '{$btr->learning_hint_brands_check|escape:'javascript'}',
                        position: 'top',
                    },
                    {
                        element: '.fn_step-1:first-child .okay_list_photo',
                        intro: '{$btr->learning_hint_brands_photo|escape:'javascript'}',
                        position: 'top',
                    },
                    {
                        element: '.fn_step-1:first-child .okay_list_brands_name',
                        intro: '{$btr->learning_hint_brands_name|escape:'javascript'}',
                        position: 'top',
                    },
                    {
                        element: '.fn_step-1:first-child .okay_list_status',
                        intro: '{$btr->learning_hint_brands_status|escape:'javascript'}',
                        position: 'left',
                    },
                    {
                        element: '.fn_step-1:first-child .okay_list_setting',
                        intro: '{$btr->learning_hint_brands_setting|escape:'javascript'}',
                        position: 'left',
                    },
                    {
                        intro: '{$btr->learning_hint_brands_add|escape:'javascript'}',

                    }

                ]
            });
            intro.start();
            intro.oncomplete(function() {
                window.location.href = 'index.php?controller=BrandAdmin&multipage=&id=1';
            });
        }
        if (RegExp('multipage', 'gi').test(window.location.search)) {
            learningBrands();
        }
    {/if}

    {if $hintId == "hint_brand"}
        function learningBrand(){
            var intro = introJs();
            intro.setOptions({
                exitOnOverlayClick: false,
                showStepNumbers: false,
                showBullets: false,
                showProgress: true,
                prevLabel: '« {$btr->learning_hint_prev|escape:'javascript'}',
                nextLabel: '{$btr->learning_hint_next|escape:'javascript'} »',
                skipLabel: '×',
                doneLabel: '{$btr->learning_hint_done|escape:'javascript'}',
                steps: [
                    {
                        intro: '{$btr->learning_hint_brand_intro|escape:'javascript'}',
                    },
                    {
                        element: '.fn_step-1',
                        intro: '{$btr->learning_hint_brand_name|escape:'javascript'}',
                        position: 'bottom',
                    },
                    {
                        element: '.fn_step-2',
                        intro: '{$btr->learning_hint_brand_url|escape:'javascript'}',
                        position: 'bottom',
                    },
                    {
                        element: '.fn_step-3',
                        intro: '{$btr->learning_hint_brand_activity|escape:'javascript'}',
                        position: 'left',
                    },
                    {
                        element: '.fn_step-4',
                        intro: '{$btr->learning_hint_brand_images|escape:'javascript'}',
                    },
                    {
                        element: '.fn_step-5',
                        intro: '{$btr->learning_hint_meta_data|escape:'javascript'}',
                    },
                    {
                        element: '.fn_step-6',
                        intro: '{$btr->learning_hint_brand_descriptions|escape:'javascript'}',
                    },
                    {
                        intro: '{$btr->learning_hint_brand_finish|escape:'javascript'}',
                        position: 'bottom',
                    }
                ]
            });

            intro.start();
            intro.oncomplete(function() {
                $.ajax({
                    url: 'ajax/lesson_done.php',
                    data: {
                        lesson: 2
                    }
                })
                    .done(function() {
                        window.location.href = 'index.php?controller=LearningAdmin';
                    });
            });
        }
        if (RegExp('multipage', 'gi').test(window.location.search)) {
            learningBrand();
        }
    {/if}

    {if $hintId == "hint_features"}
        function learningFeatures(){
            var intro = introJs();
            intro.setOptions({
                exitOnOverlayClick: false,
                showStepNumbers: false,
                showBullets: false,
                showProgress: true,
                prevLabel: '« {$btr->learning_hint_prev|escape:'javascript'}',
                nextLabel: '{$btr->learning_hint_next|escape:'javascript'} »',
                skipLabel: '×',
                doneLabel: '{$btr->learning_hint_next_done|escape:'javascript'}',
                steps: [
                    {
                        intro: '{$btr->learning_hint_features_intro|escape:'javascript'}',
                    },
                    {
                        intro: '{$btr->learning_hint_features_intro2|escape:'javascript'}',
                        position: 'left',
                    },
                    {
                        element: '.fn_step-1',
                        intro: '{$btr->learning_hint_features_sorting|escape:'javascript'}',
                        position: 'top',
                    },
                    {
                        element: '.fn_step-2:first-child',
                        intro: '{$btr->learning_hint_features_item|escape:'javascript'}',
                        position: 'top',
                    },
                    {
                        element: '.fn_step-2:first-child .move_zone',
                        intro: '{$btr->learning_hint_features_move_zone|escape:'javascript'}',
                        position: 'top',
                    },
                    {
                        element: '.fn_step-2:first-child .okay_list_check',
                        intro: '{$btr->learning_hint_features_check|escape:'javascript'}',
                        position: 'top',
                    },
                    {
                        element: '.fn_step-2:first-child .okay_list_features_name',
                        intro: '{$btr->learning_hint_features_name|escape:'javascript'}',
                        position: 'top',
                    },
                    {
                        element: '.fn_step-2:first-child .okay_list_features_tag',
                        intro: '{$btr->learning_hint_features_category|escape:'javascript'}',
                        position: 'top',
                    },
                    {
                        element: '.fn_step-2:first-child .okay_list_status',
                        intro: '{$btr->learning_hint_features_setting|escape:'javascript'}',
                        position: 'left',
                    },
                    {
                        element: '.fn_step-2:first-child .okay_list_url_status',
                        intro: '{$btr->learning_hint_features_status|escape:'javascript'}',
                        position: 'left',
                    },
                    {
                        element: '.fn_step-2:first-child .okay_list_boding:nth-last-child(2)',
                        intro: '{$btr->learning_hint_features_status_2|escape:'javascript'}',
                        position: 'left',
                    },
                    {
                        intro: '{$btr->learning_hint_features_add|escape:'javascript'}',

                    }

                ]
            });
            intro.start();
            intro.oncomplete(function() {
                window.location.href = 'index.php?controller=FeatureAdmin&multipage=&id=1';
            });
        }
        if (RegExp('multipage', 'gi').test(window.location.search)) {
            learningFeatures();
        }
    {/if}

    {if $hintId == "hint_feature"}
    function learningFeature(){
        var intro = introJs();
        intro.setOptions({
            exitOnOverlayClick: false,
            showStepNumbers: false,
            showBullets: false,
            showProgress: true,
            prevLabel: '« {$btr->learning_hint_prev|escape:'javascript'}',
            nextLabel: '{$btr->learning_hint_next|escape:'javascript'} »',
            skipLabel: '×',
            doneLabel: '{$btr->learning_hint_done|escape:'javascript'}',
            steps: [
                {
                    intro: '{$btr->learning_hint_feature_intro|escape:'javascript'}',
                },
                {
                    element: '.fn_step-1',
                    intro: '{$btr->learning_hint_feature_name|escape:'javascript'}',
                    position: 'bottom',
                },
                {
                    element: '.fn_step-2',
                    intro: '{$btr->learning_hint_feature_url|escape:'javascript'}',
                    position: 'bottom',
                },
                {
                    element: '.fn_step-3',
                    intro: '{$btr->learning_hint_feature_link|escape:'javascript'}',
                },
                {
                    element: '.fn_step-4',
                    intro: '{$btr->learning_hint_feature_activity|escape:'javascript'}',
                    position: 'left',
                },
                {
                    element: '.fn_step-5',
                    intro: '{$btr->learning_hint_feature_filter|escape:'javascript'}',
                    position: 'right',
                },
                {
                    element: '.fn_step-6',
                    intro: '{$btr->learning_hint_features_meta_data|escape:'javascript'}',
                    position: 'left',
                },
                {
                    intro: '{$btr->learning_hint_feature_list|escape:'javascript'}',
                },
                {
                    element: '.fn_step-7',
                    intro: '{$btr->learning_hint_feature_list_add|escape:'javascript'}',
                },
                {
                    element: '.fn_step-8',
                    intro: '{$btr->learning_hint_feature_list_sorting|escape:'javascript'}',
                },
                {
                    element: '.fn_step-9:first-child',
                    intro: '{$btr->learning_hint_feature_item|escape:'javascript'}',
                },
                {
                    element: '.fn_step-9:first-child .move_zone',
                    intro: '{$btr->learning_hint_feature_move_zone|escape:'javascript'}',
                    position: 'top',
                },
                {
                    element: '.fn_step-9:first-child .feature_value_name',
                    intro: '{$btr->learning_hint_feature_list_name|escape:'javascript'}',
                    position: 'top',
                },
                {
                    element: '.fn_step-9:first-child .feature_value_translit',
                    intro: '{$btr->learning_hint_feature_value_translit|escape:'javascript'}',
                    position: 'top',
                },
                {
                    element: '.fn_step-9:first-child .feature_value_products_num',
                    intro: '{$btr->learning_hint_feature_value_products_num|escape:'javascript'}',
                    position: 'top',
                },
                {
                    element: '.fn_step-9:first-child .feature_value_index',
                    intro: '{$btr->learning_hint_feature_value_index|escape:'javascript'}',
                    position: 'top',
                },
                {
                    element: '.fn_step-10',
                    intro: '{$btr->learning_hint_feature_union|escape:'javascript'}',
                },
                {
                    intro: '{$btr->learning_hint_feature_finish|escape:'javascript'}',
                    position: 'bottom',
                }
            ]
        });

        intro.start();
        intro.oncomplete(function() {
            $.ajax({
                url: 'ajax/lesson_done.php',
                data: {
                    lesson: 3
                }
            })
                .done(function() {
                    window.location.href = 'index.php?controller=LearningAdmin';
                });
        });
    }
    if (RegExp('multipage', 'gi').test(window.location.search)) {
        learningFeature();
    }
    {/if}

    {if $hintId == "hint_products"}
        function learningProducts(){
            var intro = introJs();
            intro.setOptions({
                exitOnOverlayClick: false,
                showStepNumbers: false,
                showBullets: false,
                showProgress: true,
                prevLabel: '« {$btr->learning_hint_prev|escape:'javascript'}',
                nextLabel: '{$btr->learning_hint_next|escape:'javascript'} »',
                skipLabel: '×',
                doneLabel: '{$btr->learning_hint_next_done|escape:'javascript'}',
                steps: [
                    {
                        intro: '{$btr->learning_hint_products_intro|escape:'javascript'}',
                    },
                    {
                        intro: '{$btr->learning_hint_products_intro2|escape:'javascript'}',
                        position: 'left',
                    },
                    {
                        element: '.fn_step-0',
                        intro: '{$btr->learning_hint_products_sorting|escape:'javascript'}',
                        position: 'top',
                    },
                    {
                        element: '.fn_step_sorting',
                        intro: '{$btr->learning_hint_products_sorting2|escape:'javascript'}',
                        position: 'bottom',
                    },
                    {
                        element: '.fn_step-1:first-child',
                        intro: '{$btr->learning_hint_products_item|escape:'javascript'}',
                        position: 'top',
                    },
                    {
                        element: '.fn_step-1:first-child .move_zone',
                        intro: '{$btr->learning_hint_products_move_zone|escape:'javascript'}',
                        position: 'top',
                    },
                    {
                        element: '.fn_step-1:first-child .okay_list_check',
                        intro: '{$btr->learning_hint_products_check|escape:'javascript'}',
                        position: 'top',
                    },
                    {
                        element: '.fn_step-1:first-child .okay_list_photo',
                        intro: '{$btr->learning_hint_products_photo|escape:'javascript'}',
                        position: 'top',
                    },
                    {
                        element: '.fn_step-1:first-child .okay_list_name',
                        intro: '{$btr->learning_hint_products_name|escape:'javascript'}',
                        position: 'top',
                    },
                    {
                        element: '.fn_step-1:first-child .okay_list_price',
                        intro: '{$btr->learning_hint_products_list_price|escape:'javascript'}',
                        position: 'top',
                    },
                    {
                        element: '.fn_step-1:first-child .okay_list_count',
                        intro: '{$btr->learning_hint_products_list_count|escape:'javascript'}',
                        position: 'top',
                    },
                    {
                        element: '.fn_step-1:first-child .okay_list_status',
                        intro: '{$btr->learning_hint_products_status|escape:'javascript'}',
                        position: 'left',
                    },
                    {
                        element: '.fn_step-1:first-child .okay_list_products_setting',
                        intro: '{$btr->learning_hint_products_setting|escape:'javascript'}',
                        position: 'left',
                    },
                    {
                        intro: '{$btr->learning_hint_products_add|escape:'javascript'}',

                    }

                ]
            });
            intro.start();
            intro.oncomplete(function() {
                window.location.href = 'index.php?controller=ProductAdmin&multipage=&id=1';
            });
        }
        if (RegExp('multipage', 'gi').test(window.location.search)) {
            learningProducts();
        }
    {/if}

{if $hintId == "hint_product"}
    function learningProduct(){
        var intro = introJs();
        intro.setOptions({
        exitOnOverlayClick: false,
        showStepNumbers: false,
        showBullets: false,
        showProgress: true,
        prevLabel: '« {$btr->learning_hint_prev|escape:'javascript'}',
        nextLabel: '{$btr->learning_hint_next|escape:'javascript'} »',
        skipLabel: '×',
        doneLabel: '{$btr->learning_hint_done|escape:'javascript'}',
        steps: [
            {
                intro: '{$btr->learning_hint_product_intro|escape:'javascript'}',
            },
            {
                element: '.fn_step-1',
                intro: '{$btr->learning_hint_product_name|escape:'javascript'}',
                position: 'bottom',
            },
            {
                element: '.fn_step-2',
                intro: '{$btr->learning_hint_product_url|escape:'javascript'}',
                position: 'bottom',
            },
            {
                element: '.fn_step-3',
                intro: '{$btr->learning_hint_product_activity|escape:'javascript'}',
                position: 'left',
            },
            {
                element: '.fn_step-4',
                intro: '{$btr->learning_hint_product_hits|escape:'javascript'}',
                position: 'left',
            },
            {
                element: '.fn_step-5',
                intro: '{$btr->learning_hint_product_images|escape:'javascript'}',
            },
            {
                element: '.fn_step-6',
                intro: '{$btr->learning_hint_product_brand|escape:'javascript'}',
                position: 'left',
            },
            {
                element: '.fn_step-7',
                intro: '{$btr->learning_hint_product_category|escape:'javascript'}',
                position: 'left',
            },
            {
                element: '.fn_step-8',
                intro: '{$btr->learning_hint_product_variant|escape:'javascript'}',
                position: 'bottom',
            },
            {
                element: '.fn_step-9',
                intro: '{$btr->learning_hint_product_promo|escape:'javascript'}',
                position: 'top',
            },
            {
                element: '.fn_step-10',
                intro: '{$btr->learning_hint_product_rating|escape:'javascript'}',
                position: 'left',
            },
            {
                element: '.fn_step-11',
                intro: '{$btr->learning_hint_product_features|escape:'javascript'}',
                position: 'right',
            },
            {
                element: '.fn_step-12',
                intro: '{$btr->learning_hint_product_recommended|escape:'javascript'}',
                position: 'left',
            },
            {
                element: '.fn_step-13',
                intro: '{$btr->learning_hint_meta_data|escape:'javascript'}',
            },
            {
                element: '.fn_step-14',
                intro: '{$btr->learning_hint_product_descriptions|escape:'javascript'}',
            },
            {
                intro: '{$btr->learning_hint_product_finish|escape:'javascript'}',
                position: 'bottom',
            }
        ]
        });

        intro.start();
        intro.oncomplete(function() {
            $.ajax({
                url: 'ajax/lesson_done.php',
                data: {
                    lesson: 4
                }
            })
                .done(function() {
                    window.location.href = 'index.php?controller=LearningAdmin';
                });
        });
    }
    if (RegExp('multipage', 'gi').test(window.location.search)) {
        learningProduct();
    }
{/if}

{if $hintId == "hint_currency"}
    function learningCurrency(){
        var intro = introJs();
        intro.setOptions({
            exitOnOverlayClick: false,
            showStepNumbers: false,
            showBullets: false,
            showProgress: true,
            prevLabel: '« {$btr->learning_hint_prev|escape:'javascript'}',
            nextLabel: '{$btr->learning_hint_next|escape:'javascript'} »',
            skipLabel: '×',
            doneLabel: '{$btr->learning_hint_done|escape:'javascript'}',
            steps: [
                {
                    intro: '{$btr->learning_hint_currency_intro|escape:'javascript'}',
                },
                {
                    element: '.fn_step-1',
                    intro: '{$btr->learning_hint_currency_start|escape:'javascript'}',
                    position: 'top',
                },
                {
                    element: '.fn_step-2:first-child',
                    intro: '{$btr->learning_hint_currency_intro2|escape:'javascript'}',
                },
                {
                    element: '.fn_step-2:first-child .okay_list_currency_name',
                    intro: '{$btr->learning_hint_currency_name|escape:'javascript'}',
                },
                {
                    element: '.fn_step-2:first-child .okay_list_currency_iso',
                    intro: '{$btr->learning_hint_currency_sign|escape:'javascript'}',
                    position: 'bottom',
                },
                {
                    element: '.fn_step-2:first-child .okay_list_currency_sign',
                    intro: '{$btr->learning_hint_currency_iso|escape:'javascript'}',
                    position: 'bottom',
                },
                {
                    element: '.fn_step-2:first-child .okay_list_currency_exchange .okay_list_currency_exchange_item',
                    intro: '{$btr->learning_hint_currency_course|escape:'javascript'}',
                },
                {
                    element: '.fn_step-2:nth-child(2) .okay_list_currency_exchange .okay_list_currency_exchange_item',
                    intro: '{$btr->learning_hint_currency_course2|escape:'javascript'}',
                },
                {
                    element: '.fn_step-2:nth-child(1) .okay_list_status',
                    intro: '{$btr->learning_hint_currency_activity|escape:'javascript'}',
                },
                {
                    element: '.fn_step-2:nth-child(1) .cur_settings',
                    intro: '{$btr->learning_hint_currency_cent|escape:'javascript'}',
                    position: 'left',
                },
                {
                    intro: '{$btr->learning_hint_currency_finish|escape:'javascript'}',
                    position: 'center',
                }
            ]
        });

        intro.start();
        intro.oncomplete(function() {
            $.ajax({
                url: 'ajax/lesson_done.php',
                data: {
                    lesson: 9
                }
            })
                .done(function() {
                    window.location.href = 'index.php?controller=LearningAdmin';
                });
        });
    }
    if (RegExp('multipage', 'gi').test(window.location.search)) {
        learningCurrency();
    }
{/if}

    {if $hintId == "hint_deliveries"}
    function learningDeliveries(){
        var intro = introJs();
        intro.setOptions({
            exitOnOverlayClick: false,
            showStepNumbers: false,
            showBullets: false,
            showProgress: true,
            prevLabel: '« {$btr->learning_hint_prev|escape:'javascript'}',
            nextLabel: '{$btr->learning_hint_next|escape:'javascript'} »',
            skipLabel: '×',
            doneLabel: '{$btr->learning_hint_next_done|escape:'javascript'}',
            steps: [
                {
                    intro: '{$btr->learning_hint_deliveries_intro|escape:'javascript'}',
                },
                {
                    intro: '{$btr->learning_hint_deliveries_intro2|escape:'javascript'}',
                    position: 'left',
                },
                {
                    element: '.fn_step-1:first-child',
                    intro: '{$btr->learning_hint_deliveries_item|escape:'javascript'}',
                    position: 'top',
                },
                {
                    element: '.fn_step-1:first-child .move_zone',
                    intro: '{$btr->learning_hint_deliveries_move_zone|escape:'javascript'}',
                    position: 'top',
                },
                {
                    element: '.fn_step-1:first-child .okay_list_check',
                    intro: '{$btr->learning_hint_deliveries_check|escape:'javascript'}',
                    position: 'top',
                },
                {
                    element: '.fn_step-1:first-child .okay_list_delivery_photo',
                    intro: '{$btr->learning_hint_deliveries_photo|escape:'javascript'}',
                    position: 'top',
                },
                {
                    element: '.fn_step-1:first-child .okay_list_delivery_name',
                    intro: '{$btr->learning_hint_deliveries_name|escape:'javascript'}',
                    position: 'top',
                },
                {
                    element: '.fn_step-1:first-child .okay_list_delivery_condit',
                    intro: '{$btr->learning_hint_deliveries_terms|escape:'javascript'}',
                    position: 'left',
                },
                {
                    element: '.fn_step-1:first-child .okay_list_status',
                    intro: '{$btr->learning_hint_deliveries_status|escape:'javascript'}',
                    position: 'left',
                },
                {
                    intro: '{$btr->learning_hint_deliveries_add|escape:'javascript'}',

                }

            ]
        });
        intro.start();
        intro.oncomplete(function() {
            window.location.href = 'index.php?controller=DeliveryAdmin&multipage=&id=1';
        });
    }
    if (RegExp('multipage', 'gi').test(window.location.search)) {
        learningDeliveries();
    }
    {/if}

{if $hintId == "hint_delivery"}
    function learningDelivery(){
        var intro = introJs();

        intro.setOptions({
            exitOnOverlayClick: false,
            showStepNumbers: false,
            showBullets: false,
            showProgress: true,
            prevLabel: '« {$btr->learning_hint_prev|escape:'javascript'}',
            nextLabel: '{$btr->learning_hint_next|escape:'javascript'} »',
            skipLabel: '×',
            doneLabel: '{$btr->learning_hint_done|escape:'javascript'}',
            steps: [
                {
                    intro: '{$btr->learning_hint_delivery_intro|escape:'javascript'}',
                },
                {
                    element: '.fn_step-1',
                    intro: '{$btr->learning_hint_delivery_name|escape:'javascript'}',
                    position: 'bottom',
                },
                {
                    element: '.fn_step-2',
                    intro: '{$btr->learning_hint_delivery_activity|escape:'javascript'}',
                    position: 'left',
                },
                {
                    element: '.fn_step-3',
                    intro: '{$btr->learning_hint_delivery_images|escape:'javascript'}',
                },
                {
                    element: '.fn_step-4',
                    intro: '{$btr->learning_hint_delivery_type|escape:'javascript'}',
                },
                {
                    element: '.fn_step-5',
                    intro: '{$btr->learning_hint_delivery_module|escape:'javascript'}',
                },
                {
                    element: '.fn_step-6',
                    intro: '{$btr->learning_hint_delivery_payments|escape:'javascript'}',
                },
                {
                    element: '.fn_step-7',
                    intro: '{$btr->learning_hint_delivery_descriptions|escape:'javascript'}',
                },
                {
                    intro: '{$btr->learning_hint_delivery_finish|escape:'javascript'}',
                    position: 'bottom',
                }
            ]
        });

        intro.start();
        intro.oncomplete(function() {
            $.ajax({
                url: 'ajax/lesson_done.php',
                data: {
                    lesson: 10
                }
            })
                .done(function() {
                    window.location.href = 'index.php?controller=LearningAdmin';
                });
        });
    }
    if (RegExp('multipage', 'gi').test(window.location.search)) {
        learningDelivery();
    }
{/if}

    {if $hintId == "hint_payments"}
    function learningPayments(){
        var intro = introJs();
        intro.setOptions({
            exitOnOverlayClick: false,
            showStepNumbers: false,
            showBullets: false,
            showProgress: true,
            prevLabel: '« {$btr->learning_hint_prev|escape:'javascript'}',
            nextLabel: '{$btr->learning_hint_next|escape:'javascript'} »',
            skipLabel: '×',
            doneLabel: '{$btr->learning_hint_next_done|escape:'javascript'}',
            steps: [
                {
                    intro: '{$btr->learning_hint_payments_intro|escape:'javascript'}',
                },
                {
                    intro: '{$btr->learning_hint_payments_intro2|escape:'javascript'}',
                    position: 'left',
                },
                {
                    element: '.fn_step-1:first-child',
                    intro: '{$btr->learning_hint_payments_item|escape:'javascript'}',
                    position: 'top',
                },
                {
                    element: '.fn_step-1:first-child .move_zone',
                    intro: '{$btr->learning_hint_payments_move_zone|escape:'javascript'}',
                    position: 'top',
                },
                {
                    element: '.fn_step-1:first-child .okay_list_check',
                    intro: '{$btr->learning_hint_payments_check|escape:'javascript'}',
                    position: 'top',
                },
                {
                    element: '.fn_step-1:first-child .okay_list_delivery_photo',
                    intro: '{$btr->learning_hint_payments_photo|escape:'javascript'}',
                    position: 'top',
                },
                {
                    element: '.fn_step-1:first-child .okay_list_delivery_name',
                    intro: '{$btr->learning_hint_payments_name|escape:'javascript'}',
                    position: 'top',
                },
                {
                    element: '.fn_step-1:first-child .okay_list_status',
                    intro: '{$btr->learning_hint_payments_status|escape:'javascript'}',
                    position: 'left',
                },
                {
                    intro: '{$btr->learning_hint_payments_add|escape:'javascript'}',

                }

            ]
        });
        intro.start();
        intro.oncomplete(function() {
            window.location.href = 'index.php?controller=PaymentMethodAdmin&multipage=&id=1';
        });
    }
    if (RegExp('multipage', 'gi').test(window.location.search)) {
        learningPayments();
    }
    {/if}

{if $hintId == "hint_payment"}
    function learningPayment(){
        var intro = introJs();

        intro.setOptions({
            exitOnOverlayClick: false,
            showStepNumbers: false,
            showBullets: false,
            showProgress: true,
            prevLabel: '« {$btr->learning_hint_prev|escape:'javascript'}',
            nextLabel: '{$btr->learning_hint_next|escape:'javascript'} »',
            skipLabel: '×',
            doneLabel: '{$btr->learning_hint_done|escape:'javascript'}',
            steps: [
                {
                    intro: '{$btr->learning_hint_payment_intro|escape:'javascript'}',
                },
                {
                    element: '.fn_step-1',
                    intro: '{$btr->learning_hint_payment_name|escape:'javascript'}',
                    position: 'bottom',
                },
                {
                    element: '.fn_step-2',
                    intro: '{$btr->learning_hint_payment_activity|escape:'javascript'}',
                    position: 'left',
                },
                {
                    element: '.fn_step-3',
                    intro: '{$btr->learning_hint_payment_images|escape:'javascript'}',
                },
                {
                    element: '.fn_step-4',
                    intro: '{$btr->learning_hint_payment_type|escape:'javascript'}',
                    position: 'left',
                },
                {
                    element: '.fn_step-6',
                    intro: '{$btr->learning_hint_payment_delivery|escape:'javascript'}',
                },
                {
                    element: '.fn_step-7',
                    intro: '{$btr->learning_hint_payment_descriptions|escape:'javascript'}',
                },
                {
                    intro: '{$btr->learning_hint_payment_finish|escape:'javascript'}',
                    position: 'bottom',
                }
            ]
        });

        intro.start();
        intro.oncomplete(function() {
            $.ajax({
                url: 'ajax/lesson_done.php',
                data: {
                    lesson: 11
                }
            })
                .done(function() {
                    window.location.href = 'index.php?controller=LearningAdmin';
                });
        });
    }
    if (RegExp('multipage', 'gi').test(window.location.search)) {
        learningPayment();
    }
{/if}

{if $hintId == "hint_settings_catalog"}
    function learningSettingsCatalog(){
        var intro = introJs();

        intro.setOptions({
            exitOnOverlayClick: false,
            showStepNumbers: false,
            showBullets: false,
            showProgress: true,
            prevLabel: '« {$btr->learning_hint_prev|escape:'javascript'}',
            nextLabel: '{$btr->learning_hint_next|escape:'javascript'} »',
            skipLabel: '×',
            doneLabel: '{$btr->learning_hint_done|escape:'javascript'}',
            steps: [
                {
                    intro: '{$btr->learning_hint_settings_catalog_intro|escape:'javascript'}',
                },
                {
                    element: '.fn_step-1',
                    intro: '{$btr->learning_hint_settings_catalog__products_on_page|escape:'javascript'}',
                    position: 'bottom',
                },
                {
                    element: '.fn_step-2',
                    intro: '{$btr->learning_hint_settings_catalog_products_max|escape:'javascript'}',
                    position: 'left',
                },
                {
                    element: '.fn_step-3',
                    intro: '{$btr->learning_hint_settings_catalog_posts_on_page|escape:'javascript'}',
                },
                {
                    element: '.fn_step-4',
                    intro: '{$btr->learning_hint_settings_catalog_products_comparcion|escape:'javascript'}',
                },
                {
                    element: '.fn_step-5',
                    intro: '{$btr->learning_hint_settings_catalog_units|escape:'javascript'}',
                },
                {
                    element: '.fn_step-6',
                    intro: '{$btr->learning_hint_settings_catalog_cents|escape:'javascript'}',
                    position: 'left',
                },
                {
                    element: '.fn_step-7',
                    intro: '{$btr->learning_hint_settings_catalog_thousands|escape:'javascript'}',
                    position: 'right',
                },
                {
                    element: '.fn_step-8',
                    intro: '{$btr->learning_hint_settings_catalog_not_in_stock|escape:'javascript'}',
                    position: 'left',
                },
                {
                    element: '.fn_step-9',
                    intro: '{$btr->learning_hint_settings_catalog_preorder_not_in_stock|escape:'javascript'}',
                },
                {
                    element: '.fn_step-10',
                    intro: '{$btr->learning_hint_settings_catalog_truncate_table|escape:'javascript'}',
                },
                {
                    element: '.fn_step-11',
                    intro: '{$btr->learning_hint_settings_catalog_watermark|escape:'javascript'}',
                },
                {
                    intro: '{$btr->learning_hint_settings_catalog_finish|escape:'javascript'}',
                    position: 'bottom',
                }
            ]
        });

        intro.start();
        intro.oncomplete(function() {
            $.ajax({
                url: 'ajax/lesson_done.php',
                data: {
                    lesson: 8
                }
            })
                .done(function() {
                    window.location.href = 'index.php?controller=LearningAdmin';
                });
        });
    }
    if (RegExp('multipage', 'gi').test(window.location.search)) {
        learningSettingsCatalog();
    }
{/if}

{if $hintId == "hint_settings_notify"}
    function learningSettingsNotify(){
        var intro = introJs();

        intro.setOptions({
            exitOnOverlayClick: false,
            showStepNumbers: false,
            showBullets: false,
            showProgress: true,
            prevLabel: '« {$btr->learning_hint_prev|escape:'javascript'}',
            nextLabel: '{$btr->learning_hint_next|escape:'javascript'} »',
            skipLabel: '×',
            doneLabel: '{$btr->learning_hint_done|escape:'javascript'}',
            steps: [
                {
                    intro: '{$btr->learning_hint_settings_notify_intro|escape:'javascript'}',
                },
                {
                    element: '.fn_step-1',
                    intro: '{$btr->learning_hint_settings_notify_emails|escape:'javascript'}',
                    position: 'right',
                },
                {
                    element: '.fn_step-2',
                    intro: '{$btr->learning_hint_settings_notify_reverce|escape:'javascript'}',
                    position: 'left',
                },
                {
                    element: '.fn_step-3',
                    intro: '{$btr->learning_hint_settings_notify_comments|escape:'javascript'}',
                    position: 'right',
                },
                {
                    element: '.fn_step-4',
                    intro: '{$btr->learning_hint_settings_notify_sender_name|escape:'javascript'}',
                    position: 'left',
                },
                {
                    element: '.fn_step-5',
                    intro: '{$btr->learning_hint_settings_notify_email_lang|escape:'javascript'}',
                    position: 'right',
                },
                {
                    element: '.fn_step-6',
                    intro: '{$btr->learning_hint_settings_notify_auto_approved|escape:'javascript'}',
                    position: 'left',
                },
                {
                    element: '.fn_step-7',
                    intro: '{$btr->learning_hint_settings_notify_smtp|escape:'javascript'}',
                    position: 'top',
                },
                {
                    element: '.fn_step-8',
                    intro: '{$btr->learning_hint_settings_notify_smtp2|escape:'javascript'}',
                    position: 'top',
                },
                {
                    intro: '{$btr->learning_hint_settings_notify_finish|escape:'javascript'}',
                    position: 'center',
                }
            ]
        });

        intro.start();
        intro.oncomplete(function() {
            $.ajax({
                url: 'ajax/lesson_done.php',
                data: {
                    lesson: 7
                }
            })
                .done(function() {
                    window.location.href = 'index.php?controller=LearningAdmin';
                });
        });
    }
    if (RegExp('multipage', 'gi').test(window.location.search)) {
        learningSettingsNotify();
    }
{/if}


    {if $hintId == "hint_seo_patterns"}
    function learningSeoPatterns(){
        var intro = introJs();

        intro.setOptions({
            exitOnOverlayClick: false,
            showStepNumbers: false,
            showBullets: false,
            showProgress: true,
            prevLabel: '« {$btr->learning_hint_prev|escape:'javascript'}',
            nextLabel: '{$btr->learning_hint_next|escape:'javascript'} »',
            skipLabel: '×',
            doneLabel: '{$btr->learning_hint_done|escape:'javascript'}',
            steps: [
                {
                    intro: '{$btr->learning_hint_seo_patterns_intro|escape:'javascript'}',
                },
                {
                    intro: '{$btr->learning_hint_seo_patterns_intro2|escape:'javascript'}',
                },
                {
                    element: document.querySelector('.fn_step-1'),
                    intro: '{$btr->learning_hint_seo_patterns_list|escape:'javascript'}',
                    position: 'right',
                },
                {
                    element: document.querySelector('.fn_step-2'),
                    intro: '{$btr->learning_hint_seo_patterns_right|escape:'javascript'}',
                    position: 'left',
                },
                {
                    element: document.querySelector('.fn_step-2'),
                    intro: '{$btr->learning_hint_seo_patterns_right2|escape:'javascript'}',
                    position: 'left',
                },
                {
                    intro: '{$btr->learning_hint_seo_patterns_finish|escape:'javascript'}',
                    position: 'center',
                }
            ]
        });

        intro.start();
        intro.oncomplete(function() {
            $.ajax({
                url: 'ajax/lesson_done.php',
                data: {
                    lesson: 12
                }
            })
                .done(function() {
                    window.location.href = 'index.php?controller=LearningAdmin';
                });
        });
    }
    if (RegExp('multipage', 'gi').test(window.location.search)) {
        learningSeoPatterns();
    }
    {/if}


    {if $hintId == "hint_orders"}
        function learningOrders(){
            var intro = introJs();

            intro.setOptions({
                exitOnOverlayClick: false,
                showStepNumbers: false,
                showBullets: false,
                showProgress: true,
                prevLabel: '« {$btr->learning_hint_prev|escape:'javascript'}',
                nextLabel: '{$btr->learning_hint_next|escape:'javascript'} »',
                skipLabel: '×',
                doneLabel: '{$btr->learning_hint_next_done|escape:'javascript'}',
                steps: [
                    {
                        intro: '{$btr->learning_hint_orders_intro|escape:'javascript'}',
                    },
                    {
                        intro: '{$btr->learning_hint_orders_intro2|escape:'javascript'}',
                        position: 'left',
                    },
                    {
                        element: '.fn_step-0',
                        intro: '{$btr->learning_hint_orders_sorting|escape:'javascript'}',
                        position: 'top',
                    },
                    {
                        element: '.fn_step-1:first-child',
                        intro: '{$btr->learning_hint_orders_item|escape:'javascript'}',
                        position: 'top',
                    },
                    {
                        element: '.fn_step-1:first-child .okay_list_check',
                        intro: '{$btr->learning_hint_orders_check|escape:'javascript'}',
                        position: 'top',
                    },
                    {
                        element: '.fn_step-1:first-child .okay_list_order_number',
                        intro: '{$btr->learning_hint_orders_id|escape:'javascript'}',
                        position: 'top',
                    },
                    {
                        element: '.fn_step-1:first-child .okay_list_orders_name',
                        intro: '{$btr->learning_hint_orders_name|escape:'javascript'}',
                        position: 'top',
                    },
                    {
                        element: '.fn_step-1:first-child .okay_list_order_status',
                        intro: '{$btr->learning_hint_orders_status|escape:'javascript'}',
                        position: 'top',
                    },
                    {
                        element: '.fn_step-1:first-child .okay_list_order_product_count',
                        intro: '{$btr->learning_hint_orders_count|escape:'javascript'}',
                        position: 'top',
                    },
                    {
                        element: '.fn_step-1:first-child .okay_list_orders_price',
                        intro: '{$btr->learning_hint_orders_total|escape:'javascript'}',
                        position: 'left',
                    },
                    {
                        element: '.fn_step-1:first-child .okay_list_order_marker',
                        intro: '{$btr->learning_hint_orders_marker|escape:'javascript'}',
                        position: 'left',
                    },
                    {
                        intro: '{$btr->learning_hint_orders_add|escape:'javascript'}',
                    }

                ]
            });

            intro.start();
            intro.oncomplete(function() {
                window.location.href = 'index.php?controller=OrderAdmin&multipage=&id=1';
            });
        }
        if (RegExp('multipage', 'gi').test(window.location.search)) {
            learningOrders();
        }
    {/if}

    {if $hintId == "hint_order"}
        function learningOrder(){
            var intro = introJs();

            intro.setOptions({
                exitOnOverlayClick: false,
                showStepNumbers: false,
                showBullets: false,
                showProgress: true,
                prevLabel: '« {$btr->learning_hint_prev|escape:'javascript'}',
                nextLabel: '{$btr->learning_hint_next|escape:'javascript'} »',
                skipLabel: '×',
                doneLabel: '{$btr->learning_hint_done|escape:'javascript'}',
                steps: [
                    {
                        intro: '{$btr->learning_hint_order_intro|escape:'javascript'}',
                    },
                    {
                        intro: '{$btr->learning_hint_order_intro2|escape:'javascript'}',
                    },
                    {
                        element: '.fn_step-1',
                        intro: '{$btr->learning_hint_order_toolbar|escape:'javascript'}',
                        position: 'bottom',
                    },
                    {
                        element: '.fn_step-1 .order_toolbar__status',
                        intro: '{$btr->learning_hint_order_toolbar__status|escape:'javascript'}',
                        position: 'right',
                    },
                    {
                        element: '.fn_step-1 .order_toolbar__eye',
                        intro: '{$btr->learning_hint_order_toolbar__button|escape:'javascript'}',
                        position: 'bottom',
                    },
                    {
                        element: '.fn_step-1 .order_toolbar__print',
                        intro: '{$btr->learning_hint_order_toolbar__print|escape:'javascript'}',
                        position: 'right',
                    },
                    {
                        element: '.fn_step-1 .order_toolbar__markers',
                        intro: '{$btr->learning_hint_order_toolbar__markers|escape:'javascript'}',
                        position: 'left',
                    },
                    {
                        element: '.fn_step-2',
                        intro: '{$btr->learning_hint_order_left_content|escape:'javascript'}',
                        position: 'right',
                    },
                    {
                        element: '.fn_step-3',
                        intro: '{$btr->learning_hint_order_parameters|escape:'javascript'}',
                        position: 'right',
                    },
                    {
                        element: '.fn_step-4',
                        intro: '{$btr->learning_hint_order_right_content|escape:'javascript'}',
                        position: 'left',
                    },
                    {
                        element: '.fn_step-5',
                        intro: '{$btr->learning_hint_order_to_email|escape:'javascript'}',
                        position: 'left',
                    },
                    {
                        intro: '{$btr->learning_hint_order_finish|escape:'javascript'}',
                        position: 'center',
                    }
                ]
            });

            intro.start();
            intro.oncomplete(function() {
                $.ajax({
                    url: 'ajax/lesson_done.php',
                    data: {
                        lesson: 5
                    }
                })
                    .done(function() {
                        window.location.href = 'index.php?controller=LearningAdmin';
                    });
            });
        }
        if (RegExp('multipage', 'gi').test(window.location.search)) {
            learningOrder();
        }
    {/if}

    {if $hintId == "hint_blog"}
        function learningBlog(){
            var intro = introJs();
            intro.setOptions({
                exitOnOverlayClick: false,
                showStepNumbers: false,
                showBullets: false,
                showProgress: true,
                prevLabel: '« {$btr->learning_hint_prev|escape:'javascript'}',
                nextLabel: '{$btr->learning_hint_next|escape:'javascript'} »',
                skipLabel: '×',
                doneLabel: '{$btr->learning_hint_next_done|escape:'javascript'}',
                steps: [
                    {
                        intro: '{$btr->learning_hint_blog_intro|escape:'javascript'}',
                    },
                    {
                        intro: '{$btr->learning_hint_blog_intro2|escape:'javascript'}',
                        position: 'left',
                    },
                    {
                        element: '.fn_step-0',
                        intro: '{$btr->learning_hint_blog_sorting|escape:'javascript'}',
                        position: 'right',
                    },
                    {
                        element: '.fn_step-1:first-child',
                        intro: '{$btr->learning_hint_blog_item|escape:'javascript'}',
                        position: 'top',
                    },
                    {
                        element: '.fn_step-1:first-child .okay_list_check',
                        intro: '{$btr->learning_hint_blog_check|escape:'javascript'}',
                        position: 'top',
                    },
                    {
                        element: '.fn_step-1:first-child .okay_list_photo',
                        intro: '{$btr->learning_hint_blog_photo|escape:'javascript'}',
                        position: 'top',
                    },
                    {
                        element: '.fn_step-1:first-child .okay_list_blog_name',
                        intro: '{$btr->learning_hint_blog_name|escape:'javascript'}',
                        position: 'top',
                    },
                    {
                        element: '.fn_step-1:first-child .okay_list_status',
                        intro: '{$btr->learning_hint_blog_status|escape:'javascript'}',
                        position: 'left',
                    },
                    {
                        element: '.fn_step-1:first-child .okay_list_blog_setting',
                        intro: '{$btr->learning_hint_blog_setting|escape:'javascript'}',
                        position: 'left',
                    },
                    {
                        intro: '{$btr->learning_hint_blog_add|escape:'javascript'}',
                    }

                ]
            });
            intro.start();
            intro.oncomplete(function() {
                window.location.href = 'index.php?controller=PostAdmin&multipage=&id=1';
            });
        }
        if (RegExp('multipage', 'gi').test(window.location.search)) {
            learningBlog();
        }
    {/if}

    {if $hintId == "hint_post"}
        function learningPost(){
            var intro = introJs();
            intro.setOptions({
                exitOnOverlayClick: false,
                showStepNumbers: false,
                showBullets: false,
                showProgress: true,
                prevLabel: '« {$btr->learning_hint_prev|escape:'javascript'}',
                nextLabel: '{$btr->learning_hint_next|escape:'javascript'} »',
                skipLabel: '×',
                doneLabel: '{$btr->learning_hint_done|escape:'javascript'}',
                steps: [
                    {
                        intro: '{$btr->learning_hint_post_intro|escape:'javascript'}',
                    },
                    {
                        element: '.fn_step-1',
                        intro: '{$btr->learning_hint_post_name|escape:'javascript'}',
                        position: 'bottom',
                    },
                    {
                        element: '.fn_step-2',
                        intro: '{$btr->learning_hint_post_url|escape:'javascript'}',
                        position: 'bottom',
                    },
                    {
                        element: '.fn_step-3',
                        intro: '{$btr->learning_hint_post_activity|escape:'javascript'}',
                        position: 'left',
                    },
                    {
                        element: '.fn_step-4',
                        intro: '{$btr->learning_hint_post_images|escape:'javascript'}',
                        position: 'right',
                    },
                    {
                        element: '.fn_step-5',
                        intro: '{$btr->learning_hint_post_parameters|escape:'javascript'}',
                        position: 'left',
                    },
                    {
                        element: '.fn_step-6',
                        intro: '{$btr->learning_hint_post_product|escape:'javascript'}',
                        position: 'left',
                    },
                    {
                        element: '.fn_step-7',
                        intro: '{$btr->learning_hint_meta_data|escape:'javascript'}',
                        position: 'top',
                    },
                    {
                        element: '.fn_step-8',
                        intro: '{$btr->learning_hint_post_descriptions|escape:'javascript'}',
                        position: 'top',
                    },
                    {
                        intro: '{$btr->learning_hint_post_finish|escape:'javascript'}',
                        position: 'center',
                    }
                ]
            });

            intro.start();
            intro.oncomplete(function() {
                $.ajax({
                    url: 'ajax/lesson_done.php',
                    data: {
                        lesson: 6
                    }
                })
                    .done(function() {
                        window.location.href = 'index.php?controller=LearningAdmin';
                    });
            });
        }
        if (RegExp('multipage', 'gi').test(window.location.search)) {
            learningPost();
        }
    {/if}

</script>

