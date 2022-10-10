<?php

namespace eloquentFilter\QueryFilter\Core;

use eloquentFilter\QueryFilter\HelperEloquentFilter;
use Illuminate\Database\Eloquent\Builder;

/**
 * Class QueryFilterBuilder.
 */
class QueryFilterBuilder
{

    use HelperEloquentFilter;
    /**
     * @var \eloquentFilter\QueryFilter\Core\QueryFilterCoreBuilder
     */

    public QueryFilterCore $core;

    /**
     * @var \eloquentFilter\QueryFilter\Core\RequestFilter
     */

    public RequestFilter $request;

    /**
     * @var \eloquentFilter\QueryFilter\Core\QueryBuilderWrapper
     */
    public QueryBuilderWrapper $builder;

    /**
     * @param \eloquentFilter\QueryFilter\Core\QueryFilterCore $core
     * @param \eloquentFilter\QueryFilter\Core\RequestFilter $requestFilter
     */
    public function __construct(QueryFilterCore $core, RequestFilter $requestFilter)
    {
        $this->core = $core;
        $this->request = $requestFilter;
    }

    //todo we had better make some methods on apply base on operation( 1-request(....)  2-filter 3- response

    /**
     * @param Builder $builder
     * @param array|null $request
     * @param array|null $ignore_request
     * @param array|null $accept_request
     * @param array|null $detect_injected
     *
     * @return void
     */
    public function apply(Builder $builder, array $request = null, array $ignore_request = null, array $accept_request = null, array $detect_injected = null)
    {

        $this->builder = QueryBuilderWrapperFactory::createQueryBuilder($builder);

        if (!empty($request)) {
            $this->request->setRequest($request);
        }

        if (config('eloquentFilter.enabled') == false || empty($this->request->getRequest())) {
            return;
        }

        $this->requestHandel($ignore_request, $accept_request, $detect_injected);
        $response = $this->resolveDetections();

        $response = $this->responseFilterHandle($response);

        return $response;
    }

    /**
     * @param null $index
     *
     * @return array|mixed|null
     */
    public function filterRequests($index = null)
    {
        if (!empty($index)) {
            return $this->request->getRequest()[$index];
        }

        return $this->request->getRequest();
    }

    /**
     * @return mixed
     */
    public function getAcceptedRequest()
    {
        return $this->request->getAcceptRequest();
    }

    /**
     * @return mixed
     */
    public function getIgnoredRequest()
    {
        return $this->request->getIgnoreRequest();
    }

    /**
     * @return mixed
     */
    public function getInjectedDetections()
    {
        return $this->core->getDetectInjected();
    }

    /**
     * @param array|null $ignore_request
     * @param array|null $accept_request
     * @param array|null $detect_injected
     * @return void
     */
    private function requestHandel(?array $ignore_request, ?array $accept_request, ?array $detect_injected): void
    {
        if (method_exists($this->builder->getModel(), 'serializeRequestFilter') && !empty($this->request->getRequest())) {

            $serializeRequestFilter = $this->builder->serializeRequestFilter($this->request->getRequest());
            $this->request->handelSerializeRequestFilter($serializeRequestFilter);

        }

        if ($alias_list_filter = $this->builder->getAliasListFilter()) {

            $this->request->makeAliasRequestFilter($alias_list_filter);
        }


        $this->request->setFilterRequests($ignore_request, $accept_request, $this->builder->getBuilder()->getModel());

        if (!empty($detect_injected)) {
            $this->core->setDetectInjected($detect_injected);
            $this->core->setDetectFactory($this->core->getDetectorFactory($this->core->getDefaultDetect(), $this->core->getDetectInjected()));
        }
    }

    /**
     * @return mixed
     */
    private function resolveDetections()
    {
        /** @see ResolverDetections */
        app()->bind('ResolverDetections', function () {
            return new ResolverDetections($this->builder->getBuilder(), $this->request->getRequest(), $this->core->getDetectFactory());
        });

        /** @see ResolverDetections::getResolverOut() */
        $response = app('ResolverDetections')->getResolverOut();
        return $response;
    }

    /**
     * @param $out
     *
     * @return mixed
     */
    public function responseFilterHandle($out)
    {
        if (method_exists($this->builder->getBuilder()->getModel(), 'ResponseFilter')) {
            return $this->builder->getBuilder()->getModel()->ResponseFilter($out);
        }

        return $out;
    }
}