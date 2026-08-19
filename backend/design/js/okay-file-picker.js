/**
 * Вибирач файлів редактора: сторінка відкривається TinyMCE в iframe.
 *
 * Вибраний файл повертається батьківському вікну через postMessage - інакше
 * діалог редактора не дізнається, що саме вибрали. Формат повідомлення
 * ({okayFilePicker: true, url}) читає file_picker_callback у tinymce_init.tpl.
 */
(function () {
    'use strict';

    var root = document.querySelector('.fn_picker');
    if (!root) {
        return;
    }

    var session = root.getAttribute('data-session');
    var path = root.getAttribute('data-path') || '';

    function post(action, body) {
        body.append('session_id', session);
        body.append('path', path);

        return fetch('index.php?controller=FilePickerAdmin@' + action, {
            method: 'POST',
            body: body,
            credentials: 'same-origin'
        }).then(function (response) {
            return response.json();
        });
    }

    root.addEventListener('click', function (event) {
        var pick = event.target.closest('.fn_picker_pick');
        if (pick) {
            window.parent.postMessage({okayFilePicker: true, url: pick.getAttribute('data-url')}, window.location.origin);
            return;
        }

        var remove = event.target.closest('.fn_picker_delete');
        if (!remove) {
            return;
        }

        if (!window.confirm(root.getAttribute('data-confirm'))) {
            return;
        }

        var body = new FormData();
        body.append('name', remove.getAttribute('data-name'));

        post('delete', body).then(function (result) {
            if (result && result.deleted) {
                window.location.reload();
            }
        });
    });

    var file = root.querySelector('.fn_picker_file');
    if (!file) {
        return;
    }

    file.addEventListener('change', function () {
        var queue = Array.prototype.slice.call(file.files);
        if (!queue.length) {
            return;
        }

        var failed = false;

        // Послідовно, а не разом: паралельні запити з однаковим іменем файла
        // розійшлись би в гонитві за вільним ім'ям.
        queue.reduce(function (chain, item) {
            return chain.then(function () {
                var body = new FormData();
                body.append('file', item);

                return post('upload', body).then(function (result) {
                    if (!result || result.error) {
                        failed = true;
                    }
                });
            });
        }, Promise.resolve()).then(function () {
            if (failed) {
                window.alert(root.getAttribute('data-error'));
            }
            window.location.reload();
        });
    });
}());
