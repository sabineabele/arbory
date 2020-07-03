<?php

namespace Arbory\Base\Http\Middleware;

use Arbory\Base\Nodes\Node;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Class NodeActiveMiddleware
 * @package Arbory\Base\Http\Middleware
 */
class NodeActiveMiddleware
{
    /**
     * @var Node
     */
    protected $node;

    /**
     * NodeActiveMiddleware constructor.
     * @param Node $node
     */
    public function __construct(Node $node)
    {
        $this->node = $node;
    }

    /**
     * @param $request
     * @param Closure $next
     * @return mixed
     * @throws NotFoundHttpException
     */
    public function handle($request, Closure $next)
    {
        if ($this->node && ! $this->node->isActive()) {
            throw new NotFoundHttpException();
        }

        return $next($request);
    }
}
