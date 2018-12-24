<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */
declare(strict_types=1);

namespace Spiral\Cycle\Select;

use Spiral\Cycle\ORMInterface;
use Spiral\Database\Query\SelectQuery;

/**
 * Query builder and entity selector. Mocks SelectQuery.
 *
 * Trait provides the ability to transparently configure underlying loader query.
 *
 * @method QueryBuilder distinct()
 * @method QueryBuilder where(...$args);
 * @method QueryBuilder andWhere(...$args);
 * @method QueryBuilder orWhere(...$args);
 * @method QueryBuilder having(...$args);
 * @method QueryBuilder andHaving(...$args);
 * @method QueryBuilder orHaving(...$args);
 * @method QueryBuilder orderBy($expression, $direction = 'ASC');
 * @method QueryBuilder limit(int $limit)
 * @method QueryBuilder offset(int $offset)
 *
 * @method int avg($identifier) Perform aggregation (AVG) based on column or expression value.
 * @method int min($identifier) Perform aggregation (MIN) based on column or expression value.
 * @method int max($identifier) Perform aggregation (MAX) based on column or expression value.
 * @method int sum($identifier) Perform aggregation (SUM) based on column or expression value.
 *
 * @todo make it smarter
 */
final class QueryBuilder
{
    /** @var ORMInterface */
    private $orm;

    /** @var null|SelectQuery */
    private $query;

    /** @var AbstractLoader */
    private $loader;

    /** @var string|null */
    private $forward;

    /**
     * @param ORMInterface   $orm
     * @param SelectQuery    $query
     * @param AbstractLoader $loader
     */
    public function __construct(ORMInterface $orm, SelectQuery $query, AbstractLoader $loader)
    {
        $this->orm = $orm;
        $this->query = $query;
        $this->loader = $loader;
    }

    /**
     * Get currently associated query.
     *
     * @return SelectQuery|null
     */
    public function getQuery(): ?SelectQuery
    {
        return $this->query;
    }

    /**
     * @return AbstractLoader
     */
    public function getLoader(): AbstractLoader
    {
        return $this->loader;
    }

    /**
     * Select query method prefix for all "where" queries. Can route "where" to "onWhere".
     *
     * @param string $forward "where", "onWhere"
     * @return QueryBuilder
     */
    public function setForwarding(string $forward = null): self
    {
        $this->forward = $forward;

        return $this;
    }

    /**
     * Forward call to underlying target.
     *
     * @param string $func
     * @param array  $args
     * @return SelectQuery|mixed
     */
    public function __call(string $func, array $args)
    {
        $args = array_values($args);
        if (count($args) === 1 && $args[0] instanceof \Closure) {
            // $query->where(function($q) { ...
            call_user_func($args[0], $this);
            return $this;
        }

        // prepare arguments
        $result = call_user_func_array($this->targetFunc($func), $this->resolve($func, $args));

        if ($result === $this->query) {
            return $this;
        }

        return $result;
    }

    /**
     * Replace target where call with another compatible method (for example join or having).
     *
     * @param string $call
     * @return callable
     */
    protected function targetFunc(string $call): callable
    {
        if ($this->forward != null) {
            switch (strtolower($call)) {
                case "where":
                    $call = $this->forward;
                    break;
                case "orwhere":
                    $call = 'or' . ucfirst($this->forward);
                    break;
                case "andwhere":
                    $call = 'and' . ucfirst($this->forward);
                    break;
            }
        }

        return [$this->query, $call];
    }

    /**
     * Automatically modify all identifiers to mount table prefix. Provide ability to automatically resolve
     * relations.
     *
     * @param array $where
     * @return array
     */
    protected function resolve($func, array $where): array
    {
        // all of the SelectQuery functions has similar signature where
        // first argument is identifier, we will use generalized proxy

        // short array syntax
        if (count($where) === 1 && array_key_exists(0, $where) && is_array($where[0])) {
            $result = [];
            foreach ($where[0] as $key => $value) {
                if (is_string($key) && !is_int($key)) {
                    $key = $this->identifier($key);
                }

                $result[$key] = !is_array($value) ? $value : $this->resolve(null, $value);
            }

            return [$result];
        }

        // normal syntax
        if (array_key_exists(0, $where) && is_string($where[0])) {
            $where[0] = $this->identifier($where[0]);
        }

        return $where;
    }

    /**
     * Automatically resolve identifier.
     *
     * @param string $identifier
     * @return string
     */
    protected function identifier(string $identifier): string
    {
        if (strpos($identifier, '.') === false) {
            // parent element
            return sprintf('%s.%s', $this->loader->getAlias(), $identifier);
        }

        // strict format
        return str_replace('@', $this->loader->getAlias(), $identifier);
    }
}