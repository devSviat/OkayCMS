<?php

namespace Okay\Core\Security;

/**
 * Переписує SVG за білим списком елементів і атрибутів.
 *
 * Завантажений SVG виконується браузером як документ, тому він проходить
 * через санітайзер до запису на диск. Усе, чого немає в списках нижче,
 * видаляється; вхід, що не парситься, відхиляється цілком.
 */
class SvgSanitizer
{
    /** @var string[] */
    private static $allowedElements = [
        'svg', 'g', 'defs', 'title', 'desc', 'metadata',
        'path', 'rect', 'circle', 'ellipse', 'line', 'polyline', 'polygon',
        'text', 'tspan', 'textPath',
        'linearGradient', 'radialGradient', 'stop', 'pattern',
        'clipPath', 'mask', 'symbol', 'marker',
    ];

    /** @var string[] */
    private static $allowedAttributes = [
        'id', 'class', 'style', 'transform',
        'x', 'y', 'x1', 'y1', 'x2', 'y2', 'cx', 'cy', 'r', 'rx', 'ry',
        'width', 'height', 'd', 'points', 'viewBox', 'preserveAspectRatio',
        'fill', 'fill-opacity', 'fill-rule',
        'stroke', 'stroke-width', 'stroke-opacity', 'stroke-linecap',
        'stroke-linejoin', 'stroke-dasharray', 'stroke-dashoffset',
        'opacity', 'offset', 'stop-color', 'stop-opacity',
        'gradientUnits', 'gradientTransform', 'spreadMethod',
        'clip-path', 'clip-rule', 'mask',
        'font-family', 'font-size', 'font-weight', 'font-style',
        'text-anchor', 'dominant-baseline', 'letter-spacing',
        'xmlns', 'xmlns:xlink', 'version',
    ];

    public function sanitize($svg)
    {
        $svg = (string)$svg;

        if (trim($svg) === '') {
            return null;
        }

        $previous = libxml_use_internal_errors(true);

        $document = new \DOMDocument();
        $document->preserveWhiteSpace = false;
        $document->formatOutput = false;

        // LIBXML_NONET і відсутність LIBXML_NOENT не дають резолвити
        // зовнішні сутності (XXE).
        $loaded = $document->loadXML($svg, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);

        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if ($loaded === false || $document->documentElement === null) {
            return null;
        }

        if (strtolower($document->documentElement->localName) !== 'svg') {
            return null;
        }

        $this->stripDoctype($document);
        $this->cleanElement($document->documentElement);

        $result = $document->saveXML($document->documentElement);

        return $result === false ? null : $result;
    }

    /**
     * Санітайзить файл на місці. Повертає false і не чіпає файл,
     * якщо вміст не є SVG, який можна розібрати.
     */
    public function sanitizeFile($path)
    {
        $contents = @file_get_contents($path);
        if ($contents === false) {
            return false;
        }

        $clean = $this->sanitize($contents);
        if ($clean === null) {
            return false;
        }

        return file_put_contents($path, $clean) !== false;
    }

    private function stripDoctype(\DOMDocument $document)
    {
        foreach (iterator_to_array($document->childNodes) as $node) {
            if ($node->nodeType === XML_DOCUMENT_TYPE_NODE) {
                $document->removeChild($node);
            }
        }
    }

    private function cleanElement(\DOMElement $element)
    {
        foreach (iterator_to_array($element->childNodes) as $child) {
            if ($child->nodeType === XML_ELEMENT_NODE) {
                if (!in_array($child->localName, self::$allowedElements, true)) {
                    $element->removeChild($child);
                    continue;
                }

                $this->cleanElement($child);
                continue;
            }

            if ($child->nodeType === XML_PI_NODE
                || $child->nodeType === XML_COMMENT_NODE
                || $child->nodeType === XML_ENTITY_REF_NODE
            ) {
                $element->removeChild($child);
            }
        }

        foreach (iterator_to_array($element->attributes) as $attribute) {
            $name = $attribute->nodeName;

            if (stripos($name, 'on') === 0) {
                $element->removeAttributeNode($attribute);
                continue;
            }

            if (in_array($name, ['href', 'xlink:href', 'src'], true)) {
                if (!$this->isSafeUrl($attribute->nodeValue)) {
                    $element->removeAttributeNode($attribute);
                }
                continue;
            }

            if (!in_array($name, self::$allowedAttributes, true)) {
                $element->removeAttributeNode($attribute);
                continue;
            }

            if ($name === 'style' && $this->styleIsDangerous($attribute->nodeValue)) {
                $element->removeAttributeNode($attribute);
            }
        }
    }

    private function isSafeUrl($value)
    {
        $value = trim((string)$value);

        if ($value === '') {
            return false;
        }

        if ($value[0] === '#' || $value[0] === '/') {
            return true;
        }

        if (!preg_match('~^([a-zA-Z][a-zA-Z0-9+.-]*):~', $value, $matches)) {
            // Відносний шлях без схеми.
            return true;
        }

        return in_array(strtolower($matches[1]), ['http', 'https'], true);
    }

    private function styleIsDangerous($value)
    {
        $value = strtolower((string)$value);

        foreach (['javascript:', 'expression(', 'url(', '@import', 'behavior:'] as $needle) {
            if (strpos($value, $needle) !== false) {
                return true;
            }
        }

        return false;
    }
}
