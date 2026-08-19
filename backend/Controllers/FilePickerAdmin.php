<?php


namespace Okay\Admin\Controllers;


use Okay\Admin\Helpers\BackendFilePickerHelper;

/**
 * Вибирач файлів для діалогів TinyMCE: зображення, медіа й посилання.
 *
 * Сторінка відкривається в iframe через windowManager.openUrl, тому шаблон
 * самостійний і віддає вибраний URL батьківському вікну через postMessage.
 */
class FilePickerAdmin extends IndexAdmin
{
    private const PER_PAGE = 60;

    private const SCRIPT = 'backend/design/js/okay-file-picker.js';

    /** В адмінці мова зветься "ua", а в BCP-47 українська це "uk". */
    private const LANG_TAGS = ['ua' => 'uk'];

    public function fetch(BackendFilePickerHelper $filePickerHelper)
    {
        $type = $this->fileType();
        // Шлях читаємо сирим: типізований відсів прибирає скісну риску разом
        // із рештою пунктуації, і вкладена тека стає недосяжною. Межу тут
        // тримає PathResolver у помічнику.
        $path  = $this->request->getRawString('path');
        $query = trim($this->request->getRawString('q'));

        $list = $filePickerHelper->findFiles(
            $path,
            $type,
            $query,
            (int)$this->request->get('page', 'integer'),
            self::PER_PAGE
        );

        $this->design->assign('picker_type', $type);
        $this->design->assign('picker_path', $path);
        $this->design->assign('picker_parent', $filePickerHelper->parentPath($path));
        $this->design->assign('picker_query', $query);
        $this->design->assign('picker_folders', $list['folders']);
        $this->design->assign('picker_files', $list['files']);
        $this->design->assign('picker_page', $list['page']);
        $this->design->assign('picker_pages_count', $list['pagesCount']);
        $this->design->assign('picker_total', $list['total']);
        // Версія CMS між правками скрипта не змінюється, тому браузер тримав
        // би стару копію: мітка часу файла - єдине, що тут справді змінюється.
        $lang = !empty($this->manager->lang) ? $this->manager->lang : 'en';
        $this->design->assign('picker_lang', self::LANG_TAGS[$lang] ?? $lang);
        $this->design->assign('picker_script_version', (int)@filemtime($this->config->get('root_dir') . self::SCRIPT));

        $this->response->setContent($this->design->fetch('file_picker.tpl'));
    }

    public function upload(BackendFilePickerHelper $filePickerHelper)
    {
        $uploaded = $filePickerHelper->uploadFile(
            $this->request->files('file'),
            $this->postedPath()
        );

        $this->response->setContent(json_encode(
            $uploaded === false ? ['error' => true] : $uploaded
        ), RESPONSE_JSON);
    }

    public function delete(BackendFilePickerHelper $filePickerHelper)
    {
        $deleted = $filePickerHelper->deleteFile(
            $this->postedPath(),
            $this->request->post('name')
        );

        $this->response->setContent(json_encode(['deleted' => $deleted]), RESPONSE_JSON);
    }

    /**
     * Нетипізований post() віддає масив як є, а path[]=x після приведення до
     * рядка став би "Array" плюс попередження. Помічник чекає рядок.
     *
     * @return string
     */
    private function postedPath()
    {
        $path = $this->request->post('path');

        return is_scalar($path) ? (string)$path : '';
    }

    /**
     * TinyMCE передає filetype лише зі свого набору, але значення приходить з
     * рядка запиту, тож звужуємо його до відомих.
     */
    private function fileType()
    {
        $type = (string)$this->request->get('filetype', 'string');

        return in_array($type, ['image', 'media'], true) ? $type : 'file';
    }
}
