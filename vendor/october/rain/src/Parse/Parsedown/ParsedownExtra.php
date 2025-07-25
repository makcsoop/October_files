<?php namespace October\Rain\Parse\Parsedown;

use DOMElement;
use DOMDocument;

/**
 * ParsedownExtra
 */
class ParsedownExtra extends Parsedown
{
    /**
     * @var int footnoteCount
     */
    protected $footnoteCount = 0;

    /**
     * @var mixed currentAbreviation
     */
    protected $currentAbreviation;

    /**
     * @var mixed currentMeaning
     */
    protected $currentMeaning;

    /**
     * @var string regexAttribute
     */
    protected $regexAttribute = '(?:[#.][-\w]+[ ]*)';

    /**
     * __construct
     */
    public function __construct()
    {
        $this->BlockTypes[':'][] = 'DefinitionList';
        $this->BlockTypes['*'][] = 'Abbreviation';

        // identify footnote definitions before reference definitions
        array_unshift($this->BlockTypes['['], 'Footnote');

        // identify footnote markers before before links
        array_unshift($this->InlineTypes['['], 'FootnoteMarker');
    }

    /**
     * text
     */
    public function text($text)
    {
        $Elements = $this->textElements($text);

        // convert to markup
        $markup = $this->elements($Elements);

        // trim line breaks
        $markup = trim($markup, "\n");

        // merge consecutive dl elements

        $markup = preg_replace('/<\/dl>\s+<dl>\s+/', '', $markup);

        // add footnotes

        if (isset($this->DefinitionData['Footnote']))
        {
            $Element = $this->buildFootnoteElement();

            $markup .= "\n" . $this->element($Element);
        }

        return $markup;
    }

    /**
     * blockAbbreviation
     */
    protected function blockAbbreviation($Line)
    {
        if (preg_match('/^\*\[(.+?)\]:[ ]*(.+?)[ ]*$/', $Line['text'], $matches))
        {
            $this->DefinitionData['Abbreviation'][$matches[1]] = $matches[2];

            $Block = array(
                'hidden' => true,
            );

            return $Block;
        }
    }

    /**
     * blockFootnote
     */
    protected function blockFootnote($Line)
    {
        if (preg_match('/^\[\^(.+?)\]:[ ]?(.*)$/', $Line['text'], $matches))
        {
            $Block = array(
                'label' => $matches[1],
                'text' => $matches[2],
                'hidden' => true,
            );

            return $Block;
        }
    }

    /**
     * blockFootnoteContinue
     */
    protected function blockFootnoteContinue($Line, $Block)
    {
        if ($Line['text'][0] === '[' && preg_match('/^\[\^(.+?)\]:/', $Line['text']))
        {
            return;
        }

        if (isset($Block['interrupted']))
        {
            if ($Line['indent'] >= 4)
            {
                $Block['text'] .= "\n\n" . $Line['text'];

                return $Block;
            }
        }
        else
        {
            $Block['text'] .= "\n" . $Line['text'];

            return $Block;
        }
    }

    /**
     * blockFootnoteComplete
     */
    protected function blockFootnoteComplete($Block)
    {
        $this->DefinitionData['Footnote'][$Block['label']] = array(
            'text' => $Block['text'],
            'count' => null,
            'number' => null,
        );

        return $Block;
    }

    /**
     * blockDefinitionList
     */
    protected function blockDefinitionList($Line, $Block)
    {
        if (!isset($Block) || $Block['type'] !== 'Paragraph')
        {
            return;
        }

        $Element = array(
            'name' => 'dl',
            'elements' => [],
        );

        $terms = explode("\n", $Block['element']['handler']['argument']);

        foreach ($terms as $term)
        {
            $Element['elements'] []= array(
                'name' => 'dt',
                'handler' => array(
                    'function' => 'lineElements',
                    'argument' => $term,
                    'destination' => 'elements'
                ),
            );
        }

        $Block['element'] = $Element;

        $Block = $this->addDdElement($Line, $Block);

        return $Block;
    }

    /**
     * blockDefinitionListContinue
     */
    protected function blockDefinitionListContinue($Line, array $Block)
    {
        if ($Line['text'][0] === ':')
        {
            $Block = $this->addDdElement($Line, $Block);

            return $Block;
        }
        else
        {
            if (isset($Block['interrupted']) && $Line['indent'] === 0)
            {
                return;
            }

            if (isset($Block['interrupted']))
            {
                $Block['dd']['handler']['function'] = 'textElements';
                $Block['dd']['handler']['argument'] .= "\n\n";

                $Block['dd']['handler']['destination'] = 'elements';

                unset($Block['interrupted']);
            }

            $text = substr($Line['body'], min($Line['indent'], 4));

            $Block['dd']['handler']['argument'] .= "\n" . $text;

            return $Block;
        }
    }

    /**
     * blockHeader
     */
    protected function blockHeader($Line)
    {
        $Block = parent::blockHeader($Line);

        if ($Block !== null && preg_match('/[ #]*{('.$this->regexAttribute.'+)}[ ]*$/', $Block['element']['handler']['argument'], $matches, PREG_OFFSET_CAPTURE))
        {
            $attributeString = $matches[1][0];

            $Block['element']['attributes'] = $this->parseAttributeData($attributeString);

            $Block['element']['handler']['argument'] = substr($Block['element']['handler']['argument'], 0, $matches[0][1]);
        }

        return $Block;
    }

    /**
     * blockMarkup
     */
    protected function blockMarkup($Line)
    {
        if ($this->markupEscaped || $this->safeMode)
        {
            return;
        }

        if (preg_match('/^<(\w[\w-]*)(?:[ ]*'.$this->regexHtmlAttribute.')*[ ]*(\/)?>/', $Line['text'], $matches))
        {
            $element = strtolower($matches[1]);

            if (in_array($element, $this->textLevelElements))
            {
                return;
            }

            $Block = array(
                'name' => $matches[1],
                'depth' => 0,
                'element' => array(
                    'rawHtml' => $Line['text'],
                    'autobreak' => true,
                ),
            );

            $length = strlen($matches[0]);
            $remainder = substr($Line['text'], $length);

            if (trim($remainder) === '')
            {
                if (isset($matches[2]) || in_array($matches[1], $this->voidElements))
                {
                    $Block['closed'] = true;
                    $Block['void'] = true;
                }
            }
            else
            {
                if (isset($matches[2]) || in_array($matches[1], $this->voidElements))
                {
                    return;
                }
                if (preg_match('/<\/'.$matches[1].'>[ ]*$/i', $remainder))
                {
                    $Block['closed'] = true;
                }
            }

            return $Block;
        }
    }

    /**
     * blockMarkupContinue
     */
    protected function blockMarkupContinue($Line, array $Block)
    {
        if (isset($Block['closed'])) {
            return;
        }

        // Open
        if (preg_match('/^<'.$Block['name'].'(?:[ ]*'.$this->regexHtmlAttribute.')*[ ]*>/i', $Line['text'])) {
            $Block['depth'] ++;
        }

        // Close
        if (preg_match('/(.*?)<\/'.$Block['name'].'>[ ]*$/i', $Line['text'], $matches)) {
            if ($Block['depth'] > 0) {
                $Block['depth'] --;
            }
            else {
                $Block['closed'] = true;
            }
        }

        if (isset($Block['interrupted'])) {
            $Block['element']['rawHtml'] .= "\n";
            unset($Block['interrupted']);
        }

        $Block['element']['rawHtml'] .= "\n".$Line['body'];

        return $Block;
    }

    /**
     * blockMarkupComplete
     */
    protected function blockMarkupComplete($Block)
    {
        if (!isset($Block['void']))
        {
            $Block['element']['rawHtml'] = $this->processTag($Block['element']['rawHtml']);
        }

        return $Block;
    }

    /**
     * blockSetextHeader
     */
    protected function blockSetextHeader($Line, ?array $Block = null)
    {
        $Block = parent::blockSetextHeader($Line, $Block);

        if ($Block !== null && preg_match('/[ ]*{('.$this->regexAttribute.'+)}[ ]*$/', $Block['element']['handler']['argument'], $matches, PREG_OFFSET_CAPTURE))
        {
            $attributeString = $matches[1][0];

            $Block['element']['attributes'] = $this->parseAttributeData($attributeString);

            $Block['element']['handler']['argument'] = substr($Block['element']['handler']['argument'], 0, $matches[0][1]);
        }

        return $Block;
    }

    /**
     * inlineFootnoteMarker
     */
    protected function inlineFootnoteMarker($Excerpt)
    {
        if (preg_match('/^\[\^(.+?)\]/', $Excerpt['text'], $matches)) {
            $name = $matches[1];

            if (!isset($this->DefinitionData['Footnote'][$name])) {
                return;
            }

            $this->DefinitionData['Footnote'][$name]['count'] ++;

            if (!isset($this->DefinitionData['Footnote'][$name]['number'])) {
                // » &
                $this->DefinitionData['Footnote'][$name]['number'] = ++ $this->footnoteCount;
            }

            $Element = [
                'name' => 'sup',
                'attributes' => ['id' => 'fnref'.$this->DefinitionData['Footnote'][$name]['count'].':'.$name],
                'element' => [
                    'name' => 'a',
                    'attributes' => ['href' => '#fn:'.$name, 'class' => 'footnote-ref'],
                    'text' => $this->DefinitionData['Footnote'][$name]['number'],
                ],
            ];

            return [
                'extent' => strlen($matches[0]),
                'element' => $Element,
            ];
        }
    }

    /**
     * inlineLink
     */
    protected function inlineLink($Excerpt)
    {
        $Link = parent::inlineLink($Excerpt);

        $remainder = $Link !== null ? substr($Excerpt['text'], $Link['extent']) : '';

        if (preg_match('/^[ ]*{('.$this->regexAttribute.'+)}/', $remainder, $matches)) {
            $Link['element']['attributes'] += $this->parseAttributeData($matches[1]);

            $Link['extent'] += strlen($matches[0]);
        }

        return $Link;
    }

    /**
     * insertAbreviation
     */
    protected function insertAbreviation(array $Element)
    {
        if (isset($Element['text'])) {
            $Element['elements'] = self::pregReplaceElements(
                '/\b'.preg_quote($this->currentAbreviation, '/').'\b/',
                array(
                    array(
                        'name' => 'abbr',
                        'attributes' => array(
                            'title' => $this->currentMeaning,
                        ),
                        'text' => $this->currentAbreviation,
                    )
                ),
                $Element['text']
            );

            unset($Element['text']);
        }

        return $Element;
    }

    /**
     * inlineText
     */
    protected function inlineText($text)
    {
        $Inline = parent::inlineText($text);

        if (isset($this->DefinitionData['Abbreviation']))
        {
            foreach ($this->DefinitionData['Abbreviation'] as $abbreviation => $meaning)
            {
                $this->currentAbreviation = $abbreviation;
                $this->currentMeaning = $meaning;

                $Inline['element'] = $this->elementApplyRecursiveDepthFirst(
                    array($this, 'insertAbreviation'),
                    $Inline['element']
                );
            }
        }

        return $Inline;
    }

    /**
     * addDdElement
     */
    protected function addDdElement(array $Line, array $Block)
    {
        $text = substr($Line['text'], 1);
        $text = trim($text);

        unset($Block['dd']);

        $Block['dd'] = array(
            'name' => 'dd',
            'handler' => array(
                'function' => 'lineElements',
                'argument' => $text,
                'destination' => 'elements'
            ),
        );

        if (isset($Block['interrupted']))
        {
            $Block['dd']['handler']['function'] = 'textElements';

            unset($Block['interrupted']);
        }

        $Block['element']['elements'] []= & $Block['dd'];

        return $Block;
    }

    /**
     * buildFootnoteElement
     */
    protected function buildFootnoteElement()
    {
        $Element = array(
            'name' => 'div',
            'attributes' => array('class' => 'footnotes'),
            'elements' => array(
                array('name' => 'hr'),
                array(
                    'name' => 'ol',
                    'elements' => [],
                ),
            ),
        );

        uasort($this->DefinitionData['Footnote'], 'self::sortFootnotes');

        foreach ($this->DefinitionData['Footnote'] as $definitionId => $DefinitionData) {
            if (!isset($DefinitionData['number'])) {
                continue;
            }

            $text = $DefinitionData['text'];

            $textElements = parent::textElements($text);

            $numbers = range(1, $DefinitionData['count']);

            $backLinkElements = [];

            foreach ($numbers as $number) {
                $backLinkElements[] = array('text' => ' ');
                $backLinkElements[] = array(
                    'name' => 'a',
                    'attributes' => array(
                        'href' => "#fnref$number:$definitionId",
                        'rev' => 'footnote',
                        'class' => 'footnote-backref',
                    ),
                    'rawHtml' => '&#8617;',
                    'allowRawHtmlInSafeMode' => true,
                    'autobreak' => false,
                );
            }

            unset($backLinkElements[0]);

            $n = count($textElements) -1;

            if ($textElements[$n]['name'] === 'p') {
                $backLinkElements = array_merge(
                    array(
                        array(
                            'rawHtml' => '&#160;',
                            'allowRawHtmlInSafeMode' => true,
                        ),
                    ),
                    $backLinkElements
                );

                unset($textElements[$n]['name']);

                $textElements[$n] = array(
                    'name' => 'p',
                    'elements' => array_merge(
                        array($textElements[$n]),
                        $backLinkElements
                    ),
                );
            }
            else {
                $textElements[] = array(
                    'name' => 'p',
                    'elements' => $backLinkElements
                );
            }

            $Element['elements'][1]['elements'] []= array(
                'name' => 'li',
                'attributes' => array('id' => 'fn:'.$definitionId),
                'elements' => array_merge(
                    $textElements
                ),
            );
        }

        return $Element;
    }

    /**
     * parseAttributeData
     */
    protected function parseAttributeData($attributeString)
    {
        $Data = [];

        $attributes = preg_split('/[ ]+/', $attributeString, - 1, PREG_SPLIT_NO_EMPTY);

        foreach ($attributes as $attribute) {
            if ($attribute[0] === '#') {
                $Data['id'] = substr($attribute, 1);
            }
            // .
            else {
                $classes []= substr($attribute, 1);
            }
        }

        if (isset($classes)) {
            $Data['class'] = implode(' ', $classes);
        }

        return $Data;
    }

    /**
     * processTag is recursive
     */
    protected function processTag($elementMarkup)
    {
        // http://stackoverflow.com/q/1148928/200145
        libxml_use_internal_errors(true);

        $DOMDocument = new DOMDocument;

        // http://stackoverflow.com/q/11309194/200145
        $elementMarkup = preg_replace_callback('/[\x{80}-\x{10FFFF}]/u', function($match) {
            return htmlentities($match[0], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        }, $elementMarkup);

        // http://stackoverflow.com/q/4879946/200145
        $DOMDocument->loadHTML($elementMarkup);
        $DOMDocument->removeChild($DOMDocument->doctype);
        $DOMDocument->replaceChild($DOMDocument->firstChild->firstChild->firstChild, $DOMDocument->firstChild);

        $elementText = '';

        if ($DOMDocument->documentElement->getAttribute('markdown') === '1') {
            foreach ($DOMDocument->documentElement->childNodes as $Node)
            {
                $elementText .= $DOMDocument->saveHTML($Node);
            }

            $DOMDocument->documentElement->removeAttribute('markdown');

            $elementText = "\n".$this->text($elementText)."\n";
        }
        else {
            foreach ($DOMDocument->documentElement->childNodes as $Node) {
                $nodeMarkup = $DOMDocument->saveHTML($Node);

                if ($Node instanceof DOMElement && !in_array($Node->nodeName, $this->textLevelElements)) {
                    $elementText .= $this->processTag($nodeMarkup);
                }
                else {
                    $elementText .= $nodeMarkup;
                }
            }
        }

        // because we don't want for markup to get encoded
        $DOMDocument->documentElement->nodeValue = 'placeholder\x1A';

        $markup = $DOMDocument->saveHTML($DOMDocument->documentElement);
        $markup = str_replace('placeholder\x1A', $elementText, $markup);

        return $markup;
    }

    /**
     * sortFootnotes
     */
    protected function sortFootnotes($A, $B)
    {
        return $A['number'] - $B['number'];
    }
}
