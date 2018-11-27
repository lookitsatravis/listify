<?php

namespace Lookitsatravis\Listify;

use Lookitsatravis\Listify\Exceptions\InvalidQueryBuilderException;

class GetConditionStringFromQueryBuilder
{
    /**
     * Returns a raw WHERE clause based off of a Query Builder object
     * @param  $query A Query Builder instance
     * @return string
     */
    public function handle($query)
    {
        $initialQueryChunks = explode('where ', $query->toSql());
        if(count($initialQueryChunks) == 1) throw new InvalidQueryBuilderException('The Listify scope is a Query Builder object, but it has no "where", so it can\'t be used as a scope.');
        $queryChunks = explode('?', $initialQueryChunks[1]);
        $bindings = $query->getBindings();

        $theQuery = '';

        for($i = 0; $i < count($queryChunks); $i++)
        {
            // "boolean"
            // "integer"
            // "double" (for historical reasons "double" is returned in case of a float, and not simply "float")
            // "string"
            // "array"
            // "object"
            // "resource"
            // "NULL"
            // "unknown type"

            $theQuery .= $queryChunks[$i];
            if(isset($bindings[$i]))
            {
                switch(gettype($bindings[$i]))
                {
                    case "string":
                        $theQuery .= '\'' . $bindings[$i] . '\'';
                        break;
                }
            }
        }

        return $theQuery;
    }

}