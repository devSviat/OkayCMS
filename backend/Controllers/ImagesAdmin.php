<?php


namespace Okay\Admin\Controllers;


use Okay\Core\TemplateConfig\FrontTemplateConfig;
use Okay\Core\Request;
use Okay\Core\Response;
use Okay\Core\Security\SafeRedirect;
use Okay\Core\Security\SafeFileName;

class ImagesAdmin extends IndexAdmin
{

    public function fetch(FrontTemplateConfig $frontTemplateConfig, Response $response)
    {
        $currentTheme = $frontTemplateConfig->getTheme();

        $images_dir = 'design/'.$currentTheme.'/images/';
        $allowed_extentions = array('png', 'gif', 'jpg', 'jpeg', 'ico');
        $images = [];
        
        /*Сохранение изображений*/
        if ($this->request->method('post') && !is_file($images_dir.'../locked')) {
            $old_names = $this->request->post('old_name');
            $new_names = $this->request->post('new_name');
            if (is_array($old_names)) {
                foreach($old_names as $i=>$old_name){
                    $new_name = $new_names[$i];
                    $new_name = trim(pathinfo($new_name, PATHINFO_FILENAME).'.'.pathinfo($old_name, PATHINFO_EXTENSION), '.');

                    // Обидва імені склеювались з images_dir без перевірки, тому
                    // "a/../../../file" виводило rename() за межі теми.
                    $old_name = SafeFileName::basename($old_name);
                    $new_name = SafeFileName::basename($new_name);
                    if ($old_name === '' || $new_name === '') {
                        continue;
                    }

                    if(is_writable($images_dir) && is_file($images_dir.$old_name) && !is_file($images_dir.$new_name)) {
                        rename($images_dir.$old_name, $images_dir.$new_name);
                    } elseif(is_file($images_dir.$new_name) && $new_name!=$old_name) {
                        $message_error = 'name_exists';
                    }
                }
            }
            
            // trim(..., '.') знімав лише крайні крапки, тож "a/../../config/config.php"
            // проходив як є і unlink() стирав будь-який файл на диску.
            $delete_image = SafeFileName::basename($this->request->post('delete_image'));

            if (!empty($delete_image)) {
                @unlink($images_dir.$delete_image);
            }

            // Загрузка изображений
            if ($images = $this->request->files('upload_images')) {
                for($i=0; $i<count($images['name']); $i++) {
                    // Ім'я приходить від клієнта: без basename() шлях у ньому
                    // виносив move_uploaded_file() за межі каталогу теми.
                    $name = SafeFileName::basename($images['name'][$i]);
                    if($name !== ''
                        && in_array(strtolower(pathinfo($name, PATHINFO_EXTENSION)), $allowed_extentions)
                    ) {
                        move_uploaded_file($images['tmp_name'][$i], $images_dir.$name);
                    }
                }
            }
            
            if (!isset($message_error)) {
                $backUrl = $_SERVER['REQUEST_URI'];
                if (!SafeRedirect::isSameOrigin($backUrl, Request::getDomainWithProtocol())) {
                    $backUrl = Request::getRootUrl();
                }
                $response->redirectTo($backUrl);
                exit();
            } else {
                $this->design->assign('message_error', $message_error);
            }
        }
        
        // Читаем все файлы
        if ($handle = opendir($images_dir)) {
            while(false !== ($file = readdir($handle))) {
                if(is_file($images_dir.$file) && $file[0] != '.' && in_array(pathinfo($file, PATHINFO_EXTENSION), $allowed_extentions)) {
                    $image = new \stdClass;
                    $image->name = $file;
                    $image->size = filesize($images_dir.$file);
                    list($image->width, $image->height) = @getimagesize($images_dir.$file);
                    $images[$file] = $image;
                }
            }
            closedir($handle);
            ksort($images);
        }
        
        // Если нет прав на запись - передаем в дизайн предупреждение
        if (!is_writable($images_dir)) {
            $this->design->assign('message_error', 'permissions');
        } elseif (is_file($images_dir.'../locked')) {
            $this->design->assign('message_error', 'theme_locked');
        }
        
        $this->design->assign('theme', $currentTheme);
        $this->design->assign('images', $images);
        $this->design->assign('images_dir', $images_dir);
        $this->response->setContent($this->design->fetch('images.tpl'));
    }
    
}
