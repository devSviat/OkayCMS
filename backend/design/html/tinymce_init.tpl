<script type="text/javascript" src="design/js/tinymce_jq/tinymce.min.js"></script>

<script>
    $(function(){
        tinyMCE.init({literal}{{/literal}
            document_base_url: '{$rootUrl}',
            selector: "textarea.editor_large, textarea.editor_small, textarea#format-custom",
            height: 600,
            relative_urls : false,
            plugins: [
                "advlist autolink quickbars lists link image preview anchor emoticons",
                "hr visualchars codesample autosave noneditable searchreplace wordcount visualblocks",
                "code fullscreen save charmap nonbreaking",
                "insertdatetime media table paste imagetools",
            ],
            toolbar_mode: 'floating',
            mobile: 'false',
            toolbar_items_size : 'small',
            menubar:'file edit insert view format table tools',
            toolbar1: "undo redo|styleselect| fontselect |fontsizeselect | OpenAiButton |forecolor backcolor blocks | bold italic underline strikethrough blockquote | alignleft aligncenter alignright | numlist bullist checklist | table | link unlink| image media emoticons  | fullscreen preview codesample code",

                {literal}
            table_class_list:[
                {title: 'None', value: ''},
                {title: 'table_style1', value: 'table_style1'},
                {title: 'table_style2', value: 'table_style2'},
                {title: 'table_style3', value: 'table_style3'}
            ],
            image_class_list: [
                {title: 'None', value: ''},
                {title: 'image_zoom', value: 'fn_img_zoom'},
                {title: 'image_slider', value: 'fn_img_slider'},
                {title: 'image_gallery', value: 'fn_img_gallery'},
                {title: 'image_gallery 2', value: 'fn_img_gallery_2'},
                {title: 'image_style', value: 'fn_image_style'}
            ],
            link_class_list: [
                {title: 'None', value: ''},
                {title: 'Style 1', value: 'link_decor'},
                {title: 'Style 2', value: 'link_style'}
            ],
            {/literal}
            statusbar: true,
            fontsize_formats: '11px 12px 14px 16px 18px 24px 36px 48px',
            font_formats: "Arial=arial,helvetica,sans-serif;"+
            "Arial Black=arial black,avant garde;"+
            "Montserrat=Montserrat,sans-serif;"+
            "Book Antiqua=book antiqua,palatino;"+
            "Comic Sans MS=comic sans ms,sans-serif;"+
            "Courier New=courier new,courier;"+
            "Georgia=georgia,palatino;"+
            "Helvetica=helvetica;"+
            "Impact=impact,chicago;"+
            "Symbol=symbol;"+
            "Tahoma=tahoma,arial,helvetica,sans-serif;"+
            "Terminal=terminal,monaco;"+
            "Times New Roman=times new roman,times;"+
            "Trebuchet MS=trebuchet ms,geneva;"+
            "Verdana=verdana,geneva;",
            image_advtab: true,
            file_picker_types: 'file image media',
            {literal}
            // Вибирач відкривається в iframe, тому URL повертається повідомленням,
            // а не значенням: див. backend/design/js/okay-file-picker.js.
            // TinyMCE 5.0 ще не має onMessage у windowManager.openUrl, тож слухач
            // один на всі діалоги - інакше закритий без вибору залишав би свій.
            file_picker_callback: (function () {
                var pending = null;

                window.addEventListener('message', function (event) {
                    if (event.origin !== window.location.origin
                        || !event.data || event.data.okayFilePicker !== true || pending === null) {
                        return;
                    }

                    var current = pending;
                    pending = null;
                    // Другий аргумент обов'язковий: діалог посилання читає meta.text
                    // одразу після зміни поля і без об'єкта падає.
                    current.callback(event.data.url, {});
                    current.dialog.close();
                });

                return function (callback, value, meta) {
                    pending = {
                        callback: callback,
                        dialog: tinymce.activeEditor.windowManager.openUrl({
                            title: '{/literal}{$btr->file_picker_title|escape:javascript}{literal}',
                            url: '{/literal}{$rootUrl}{literal}/backend/index.php?controller=FilePickerAdmin&filetype=' + meta.filetype,
                            width: Math.min(window.innerWidth - 80, 1100),
                            height: Math.min(window.innerHeight - 80, 700)
                        })
                    };
                };
            }()),
            {/literal}

            style_formats: [
                { title: 'Headings', items: [
                        { title: 'Heading 1', format: 'h1' },
                        { title: 'Heading 2', format: 'h2' },
                        { title: 'Heading 3', format: 'h3' },
                        { title: 'Heading 4', format: 'h4' },
                        { title: 'Heading 5', format: 'h5' },
                        { title: 'Heading 6', format: 'h6' }
                    ]},
                { title: 'Inline', items: [
                        { title: 'Bold', format: 'bold' },
                        { title: 'Italic', format: 'italic' },
                        { title: 'Underline', format: 'underline' },
                        { title: 'Strikethrough', format: 'strikethrough' },
                        { title: 'Superscript', format: 'superscript' },
                        { title: 'Subscript', format: 'subscript' }
                    ]},
                { title: 'Blocks', items: [
                        { title: 'Paragraph', format: 'p' },
                        { title: 'Blockquote', format: 'blockquote' },
                        { title: 'Notice_info', block: 'div', format: 'p', classes: 'tmce_notice_info' },
                        { title: 'Notice_error', block: 'div', format: 'p', classes: 'tmce_notice_error' },
                        { title: 'Notice_success', block: 'div', format: 'p', classes: 'tmce_notice_success' },
                        { title: 'Div', format: 'div' }
                    ]}
            ],

            save_enablewhendirty: true,
            save_title: "save",
            content_css : [
                {foreach $registered_front_css as $css}
                    "{$rootUrl}/{$css}",
                {/foreach}
            ],
            body_class: "block__description block__description--style",
            /* Вміст стилізує CSS вітрини, тож тут лишились правила, доречні лише
               в редакторі: поля навколо тексту, зняте кільце :focus-visible (на
               порожньому тілі воно сходиться в смугу) і відступ списків. */
            {literal}content_style: "body.mce-content-body{padding:12px 16px;}"
                + "body.mce-content-body:focus,body.mce-content-body:focus-visible{outline:none;}"
                + "body.mce-content-body ul,body.mce-content-body ol{padding-left:1.5rem;}",{/literal}
            theme_advanced_buttons3_add : "save",
            save_onsavecallback: function() {literal}{{/literal}
                $("[type='submit']").trigger("click");
                {literal}}{/literal},

            language : "{$manager->lang|escape}",
            /* Замена тега P на BR при разбивке на абзацы
             force_br_newlines : true,
             force_p_newlines : false,
             forced_root_block : '',
             */

            setup : function(editor) {
                {if $smarty.get.controller != "SeoPatternsAdmin"}
                    editor.on('keyup change', (function() {
                        set_meta();
                    }));
                {/if}

                let textarea = $('#' + editor.id);
                if (!textarea.length) {
                    textarea = $('[name="' + editor.id + '"]');
                }

                if (textarea.data('ai_entity')) {
                    editor.ui.registry.addButton('OpenAiButton', {
                        text: 'Gpt',
                        onAction: function (_) {
                            let name = textarea.closest('form').find('[name="name"]').val();
                            let entityId = textarea.closest('form').find('[name="id"]').val();
                            generateEditorMeta(
                                editor,
                                textarea.prop('name'),
                                textarea.data('ai_entity'),
                                name,
                                entityId
                            );
                        }
                    });
                }
            }

            {literal}}{/literal});
    });


</script>
