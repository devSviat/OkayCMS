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

    public function fetch(BackendFilePickerHelper $filePickerHelper)
    {
        $type = $this->fileType();
        $path = (string)$this->request->get('path', 'string');

        $list = $filePickerHelper->findFiles(
            $path,
            $type,
            trim((string)$this->request->get('q', 'string')),
            (int)$this->request->get('page', 'integer'),
            self::PER_PAGE
        );

        $this->design->assign('picker_type', $type);
        $this->design->assign('picker_path', $path);
        $this->design->assign('picker_parent', $filePickerHelper->parentPath($path));
        $this->design->assign('picker_query', trim((string)$this->request->get('q', 'string')));
        $this->design->assign('picker_folders', $list['folders']);
        $this->design->assign('picker_files', $list['files']);
        $this->design->assign('picker_page', $list['page']);
        $this->design->assign('picker_pages_count', $list['pagesCount']);
        $this->design->assign('picker_total', $list['total']);

        $this->response->setContent($this->design->fetch('file_picker.tpl'));
    }

    public function upload(BackendFilePickerHelper $filePickerHelper)
    {
        $uploaded = $filePickerHelper->uploadFile(
            $this->request->files('file'),
            (string)$this->request->post('path')
        );

        $this->response->setContent(json_encode(
            $uploaded === false ? ['error' => true] : $uploaded
        ), RESPONSE_JSON);
    }

    public function delete(BackendFilePickerHelper $filePickerHelper)
    {
        $deleted = $filePickerHelper->deleteFile(
            (string)$this->request->post('path'),
            (string)$this->request->post('name')
        );

        $this->response->setContent(json_encode(['deleted' => $deleted]), RESPONSE_JSON);
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
