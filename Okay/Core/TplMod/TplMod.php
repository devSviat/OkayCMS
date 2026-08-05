<?php


namespace Okay\Core\TplMod;


use Okay\Core\Config;
use Okay\Core\Modules\DTO\TplChangeDTO;
use Okay\Core\ServiceLocator;
use Okay\Core\TplMod\Nodes\BaseNode;
use Okay\Core\TplMod\Nodes\HtmlCommentNode;
use Okay\Core\TplMod\Nodes\TextNode;

class TplMod
{
    private Parser $parser;
    private bool $debug;
    
    public function __construct(Parser $parser, Config $config)
    {
        $this->parser = $parser;
        $this->debug = (bool)$config->get('dev_mode');
    }

    public function buildFile($content, $mods)
    {
        $SL = ServiceLocator::getInstance();
        
        /** @var Config $config */
        $config = $SL->getService(Config::class);
        
        if ($config->get('disable_tpl_mod')) {
            return $content; // todo отключение модификаторов
        }
        
        $res = $this->parser->parse($content);
        
        $this->walkByFile($res, $mods);
        
        //print $this->build($res);die; // todo вывод содержимого файла
        
        return $this->build($res);
    }

    /**
     * @param BaseNode $node
     * @param TplChangeDTO[] $changes
     * @return void
     */
    private function walkByFile(BaseNode $node, array $changes)
    {
        foreach ($changes as $changeDTO) {
            if ($this->matches($node, $changeDTO)) {
                $this->applyMod($node, $changeDTO);
            }
        }

        if ($node->children()) {
            foreach ($node->children() as $child) {
                $this->walkByFile($child, $changes);
            }
        }
    }

    /**
     * Правило збігу анкера. Єдине місце, де воно живе: ним користуються і рендер,
     * і ModificationChecker.
     */
    public function matches(BaseNode $node, TplChangeDTO $change): bool
    {
        if (!empty($change->getFind())) {
            return strpos($node->getOriginalElement(), $change->getFind()) !== false;
        }

        if (!empty($change->getLike())) {
            return (bool)preg_match('~'.$change->getLike().'~', $node->getOriginalElement());
        }

        return false;
    }

    /**
     * @return BaseNode[] вузли, з якими збігся анкер, у порядку обходу
     */
    public function findMatches(BaseNode $node, TplChangeDTO $change): array
    {
        $matched = [];

        if ($this->matches($node, $change)) {
            $matched[] = $node;
        }

        foreach ($node->children() as $child) {
            $matched = array_merge($matched, $this->findMatches($child, $change));
        }

        return $matched;
    }

    private function applyMod(BaseNode $node, TplChangeDTO $changeDTO)
    {
        if (($node = $this->resolveTarget($node, $changeDTO)) === null) {
            return;
        }

        if (!empty($changeDTO->getAppend())) {
            $userNode = new TextNode($changeDTO->getAppend());
            if ($this->debug === true && !empty($changeDTO->getComment())) {
                $node->append(new HtmlCommentNode("<!--{$changeDTO->getComment()}-->"));
            }
            $node->append($userNode);
            if ($this->debug === true && !empty($changeDTO->getComment())) {
                $node->append(new HtmlCommentNode("<!--/{$changeDTO->getComment()}-->"));
            }
        }

        if (!empty($changeDTO->getAppendBefore())) {
            $userNode = new TextNode($changeDTO->getAppendBefore());
            if ($this->debug === true && !empty($changeDTO->getComment())) {
                $node->appendBefore(new HtmlCommentNode("<!--{$changeDTO->getComment()}-->"));
            }
            $node->appendBefore($userNode);
            if ($this->debug === true && !empty($changeDTO->getComment())) {
                $node->appendBefore(new HtmlCommentNode("<!--/{$changeDTO->getComment()}-->"));
            }
        }
        
        if (!empty($changeDTO->getPrepend())) {
            $userNode = new TextNode($changeDTO->getPrepend());
            if ($this->debug === true && !empty($changeDTO->getComment())) {
                $node->prepend(new HtmlCommentNode("<!--/{$changeDTO->getComment()}-->"));
            }
            $node->prepend($userNode);
            if ($this->debug === true && !empty($changeDTO->getComment())) {
                $node->prepend(new HtmlCommentNode("<!--{$changeDTO->getComment()}-->"));
            }
        }

        if (!empty($changeDTO->getAppendAfter())) {
            $userNode = new TextNode($changeDTO->getAppendAfter());
            if ($this->debug === true && !empty($changeDTO->getComment())) {
                $node->appendAfter(new HtmlCommentNode("<!--/{$changeDTO->getComment()}-->"));
            }
            $node->appendAfter($userNode);
            if ($this->debug === true && !empty($changeDTO->getComment())) {
                $node->appendAfter(new HtmlCommentNode("<!--{$changeDTO->getComment()}-->"));
            }
        }

        if (!empty($changeDTO->getHtml())) {
            $userNode = new TextNode($changeDTO->getHtml());
            $node->text($userNode);
            if ($this->debug === true && !empty($changeDTO->getComment())) {
                $node->prepend(new HtmlCommentNode("<!--replaced by {$changeDTO->getComment()}-->"));
            }
        }

        if (!empty($changeDTO->getText())) {
            $userNode = new TextNode($changeDTO->getText());
            $node->text($userNode);
            if ($this->debug === true && !empty($changeDTO->getComment())) {
                $node->prepend(new HtmlCommentNode("<!--replaced by {$changeDTO->getComment()}-->"));
            }
        }

        if (!empty($changeDTO->getReplace())) {
            $node->modifyElement($changeDTO->getReplace());
        }

        if ($changeDTO->isRemove()) {
            $node->remove();
        }
        unset($node);
    }
    
    /**
     * Вузол, який зрештою буде змінено: parent -> closest* -> children*.
     * null означає, що ланцюжок обірвався і вставляти немає куди.
     */
    public function resolveTarget(BaseNode $node, TplChangeDTO $change): ?BaseNode
    {
        if ($change->isParent()) {
            if (($node = $node->parent()) === null) {
                return null;
            }
        }

        if (!empty($change->getClosestFind())) {
            $find = $change->getClosestFind();
            $node = $this->closestNode($node, static fn(BaseNode $candidate): bool
                => strpos($candidate->getOriginalElement(), $find) !== false);
        } elseif (!empty($change->getClosestLike())) {
            $like = $change->getClosestLike();
            $node = $this->closestNode($node, static fn(BaseNode $candidate): bool
                => (bool)preg_match('~'.$like.'~', $candidate->getOriginalElement()));
        }

        if ($node === null) {
            return null;
        }

        if (!empty($change->getChildrenFind())) {
            return $this->findChildNode($node, $change->getChildrenFind()) ?: null;
        }

        if (!empty($change->getChildrenLike())) {
            return $this->likeChildNode($node, $change->getChildrenLike()) ?: null;
        }

        return $node;
    }

    private function closestNode(BaseNode $node, callable $matches): ?BaseNode
    {
        while ($node = $node->parent()) {
            if ($matches($node)) {
                return $node;
            }
        }

        return null;
    }

    private function findChildNode(BaseNode $node, $search)
    {
        $result = false;
        if ($children = $node->children()) {
            foreach ($children as $child) {
                if (strpos($child->getOriginalElement(), $search) !== false) {
                    return $child;
                }
                if ($result = $this->findChildNode($child, $search)) {
                    return $result;
                }
            }
        }
        return $result;
    }
    
    private function likeChildNode(BaseNode $node, $search)
    {
        $result = false;
        if ($children = $node->children()) {
            foreach ($children as $child) {
                if (preg_match('~'.$search.'~', $child->getOriginalElement())) {
                    return $child;
                }
                if ($result = $this->likeChildNode($child, $search)) {
                    return $result;
                }
            }
        }
        return $result;
    }
    
    private function build(BaseNode $node, $level = 0): string
    {
        $resultString = '';
        /** @var BaseNode $child */
        foreach ($node->children() as $child) {
            if (strpos($node->getOriginalElement(), '<textarea') === false) {
                $resultString .= PHP_EOL;
                
                // Добавляем отступы для форматирования
                for ($i=1; $i<=$level; $i++) {
                    $resultString .= '    ';
                }
            }

            $resultString .= $child->getElement();

            if (!empty($child->children())) {
                $resultString .= $this->build($child, $level+1);
            }

            if (!empty($child->getCloseTag())) {
                // Добавляем отступы для форматирования
                if (!empty($child->children()) && strpos($child->getOriginalElement(), '<textarea') === false) {
                    $resultString .= PHP_EOL;
                    for ($i = 1; $i <= $level; $i++) {
                        $resultString .= '    ';
                    }
                }
                $resultString .= $child->getCloseTag();
            }

        }
        return $resultString;
    }
    
}