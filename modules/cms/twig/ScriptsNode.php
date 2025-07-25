<?php namespace Cms\Twig;

use Twig\Node\Node as TwigNode;
use Twig\Compiler as TwigCompiler;

/**
 * ScriptsNode represents a "scripts" node
 *
 * @package october\cms
 * @author Alexey Bobkov, Samuel Georges
 */
#[\Twig\Attribute\YieldReady]
class ScriptsNode extends TwigNode
{
    /**
     * __construct
     */
    public function __construct($lineno)
    {
        parent::__construct([], [], $lineno);
    }

    /**
     * compile the node to PHP.
     */
    public function compile(TwigCompiler $compiler)
    {
        $compiler
            ->addDebugInfo($this)
            ->write("yield \$this->env->getExtension(\Cms\Twig\Extension::class)->assetsFunction('js');\n")
            ->write("yield \$this->env->getExtension(\Cms\Twig\Extension::class)->displayBlock('scripts');\n")
        ;
    }
}
