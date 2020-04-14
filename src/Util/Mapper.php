<?php declare(strict_types = 1);

namespace FredrikAlexander\Util;

class Mapper
{
    /**
     * A variation of array_map() that uses the keys of the input array
     * in the returned array
     *
     * A variable from outside the function scope is needed can be passed
     * as an optional third argument, which will be passed on to the callback
     * in a closure. Limitations: allows only one input array and one closure argument
     *
     * @param callable $func
     * @param array    $arr
     * @param mixed    $closure
     * a closure
     * @return array
     */
    public static function arrayMapAssoc(
        callable $func,
        array $arr,
        $closure = null
    ) : array {
        $callback = function ($key, $value) use ($func, $closure) {
            return [$key, $func($key, $value, $closure)];
        };

        return array_column(array_map($callback, array_keys($arr), $arr), 1, 0);
    }
}
