<?php


namespace Okay\Core\Adapters\Response;


class ImageSvg extends AbstractResponse
{

    public function getSpecialHeaders()
    {
        return [
            'Content-type: image/svg+xml',
            // Прямою навігацією SVG рендериться як документ і виконує <script>,
            // а сюди він потрапляє з файлового менеджера. Ту саму CSP ставить
            // сервер на вже згенерованому файлі; тут вона потрібна тому, що
            // перший запит обслуговує PHP, а не диск.
            "Content-Security-Policy: default-src 'none'; sandbox",
        ];
    }
    
    public function send($content)
    {
        print implode('', $content);
    }
}
