<?php

/*
 * This file is part of Twig.
 *
 * (c) 2009 Fabien Potencier
 * (c) 2009 Armin Ronacher
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
class Twig_Node_Expression_Filter extends Twig_Node_Expression
{
    public function __construct(Twig_NodeInterface $node, Twig_Node_Expression_Constant $filterName, Twig_NodeInterface $arguments, $lineno, $tag = null)
    {
        parent::__construct(array('node' => $node, 'filter' => $filterName, 'arguments' => $arguments), array(), $lineno, $tag);
    }

    public function compile(Twig_Compiler $compiler)
    {
        $name = $this->getNode('filter')->getAttribute('value');
        if (false === $filter = $compiler->getEnvironment()->getFilter($name)) {
            throw new Twig_Error_Syntax(sprintf('The filter "%s" does not exist', $name), $this->getLine());
        }

        // The default filter is intercepted when the filtered value
        // is a name (like obj) or an attribute (like obj.attr)
        // In such a case, it's compiled to {{ obj is defined ? obj|default('bar') : 'bar' }}
        if ('default' === $name && ($this->getNode('node') instanceof Twig_Node_Expression_Name || $this->getNode('node') instanceof Twig_Node_Expression_GetAttr)) {
            $compiler->raw('((');
            if ($this->getNode('node') instanceof Twig_Node_Expression_Name) {
                $testMap = $compiler->getEnvironment()->getTests();
                $compiler
                    ->raw($testMap['defined']->compile().'(')
                    ->repr($this->getNode('node')->getAttribute('name'))
                    ->raw(', $context)')
                ;
            } elseif ($this->getNode('node') instanceof Twig_Node_Expression_GetAttr) {
                $this->getNode('node')->setAttribute('is_defined_test', true);
                $compiler->subcompile($this->getNode('node'));
                $this->getNode('node')->removeAttribute('is_defined_test');
            }

            $compiler->raw(') ? (');
            $this->compileFilter($compiler, $filter);
            $compiler->raw(') : (');
            $compiler->subcompile($this->getNode('arguments')->getNode(0));
            $compiler->raw('))');
        } else {
            $this->compileFilter($compiler, $filter);
        }
    }

    protected function compileFilter(Twig_Compiler $compiler, Twig_FilterInterface $filter)
    {
        $compiler
            ->raw($filter->compile().'(')
            ->raw($filter->needsEnvironment() ? '$this->env, ' : '')
            ->raw($filter->needsContext() ? '$context, ' : '')
        ;

        $this->getNode('node')->compile($compiler);

        foreach ($this->getNode('arguments') as $node) {
            $compiler
                ->raw(', ')
                ->subcompile($node)
            ;
        }

        $compiler->raw(')');
    }
}
