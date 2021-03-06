<?php

namespace App\Classes;

use Illuminate\Support\Facades\DB;

abstract class AbstractQueryBuilderService
{
    protected $table;
    protected $defaultRecordLimit = 25;
    protected $maxRecords = 200;
    protected $hasMaxRecords = true;

    ###################################################
    ############ Operation Arrays #####################
    ###################################################
    # What includes are allowed
    protected $supportedIncludes;

    # Supported where clauses
    protected $clauseProperties;

    # How results can be ordered by
    # Key is the url parameter and value is the column and order
    protected $sortingFields;

    # An array to check what includes are required
    # if they are not found in the parameters
    protected $requiredIncludes;

    #################################################



    # Functions that will differ from service to service
    abstract protected function filter($quotes);
    abstract protected function addSelects(&$query, $includes);
    abstract protected function addJoins(&$query, $includes);
    # Parses includes from parameters
    protected function getIncludes($parameters = [])
    {
        $includes = [];

        if (isset($parameters['include'])) {
            # Splits include string into and array
            $includeParams = explode(',', $parameters['include']);
            # Intersects matching array entries
            $includes = array_intersect($this->supportedIncludes, $includeParams);
        }

        # Return array of matching entries
        return $includes;
    }

    # Gets sorting operations from parameters
    protected function getSorting($parameters = [])
    {
        $sorts = [];

        if (isset($parameters['sortby'])) {
            $sortParams = explode(',', $parameters['sortby']);

            # Iterates through supported sorting fields
            foreach ($this->sortingFields as $sortKey=>$sortVal) {
                # Iterates through parameter array
                foreach ($sortParams as $param) {
                    if ($param == $sortKey) {
                        $sorts[$sortVal] = $sortKey;
                    }
                }
            }
        }
        return $sorts;
    }

    # Gets where clauses
    protected function getWhereClauses($parameters = [])
    {
        $clauses = [];

        # Iterates through supported where clause types
        foreach ($this->clauseProperties as $clauseType=>$clauseArray) {
            # Iterates through each operation in a clause type
            foreach ($clauseArray as $clauseName => $clause) {

                 # Checks if a clause exists in the parameters array
                if (in_array($clause, array_keys($parameters))) {
                    $clauses[$clauseType][$clauseName] = $parameters[$clause];
                }
            }
        }

        return $clauses;
    }

    # Get record limit from parameters
    protected function getLimit($parameters=[])
    {
        # Set default
        if (!isset($parameters['limit']) || !is_numeric($parameters['limit']) || $parameters['limit'] > $this->maxRecords) {
            return $this->defaultRecordLimit;
        }

        $limit = floor($parameters['limit']);

        return $limit;
    }

    # Gets the offset multiplier from parameters
    # User gives a page, then use the number to get the multiplier
    protected function getOffsetMultiplier($parameters=[])
    {
        if (!isset($parameters['page']) || !is_numeric($parameters['page'])) {
            return 0;
        }

        $page = floor($parameters['page'] - 1);

        return $page < 0 ? 0 : $page;
    }

    # Builds a query based on what is passed to it
    protected function buildQuery($parameters = [])
    {
        $basicQuery = DB::table($this->table);

        $includes = $this->getIncludes($parameters);
        $clauses = $this->getWhereClauses($parameters);
        $sorts = $this->getSorting($parameters);
        $limit = $this->getLimit($parameters);
        $offsetMultiplier = $this->getOffsetMultiplier($parameters);

        # Passes $includes by reference
        $this->addMissingIncludes($includes, $clauses, $sorts);


        #################################
        # Passes $basicQuery by reference
        #################################
        $this->addSelects($basicQuery, $includes);
        $this->addJoins($basicQuery, $includes);
        $this->addWhereClauses($basicQuery, $clauses);
        $this->addOrderBys($basicQuery, $sorts);
        $this->addLimit($basicQuery, $limit);
        $this->addOffset($basicQuery, $limit, $offsetMultiplier);
        #################################

        return $basicQuery;
    }

    # Adds includes based on the arguments passed in the request
    protected function addMissingIncludes(&$includes, $clauses, $sorts)
    {
        $operationArray = [];

        if (isset($clauses['matchClauses'])) {
            $operationArray = array_merge($operationArray, array_keys($clauses['matchClauses']));
        }
        if (isset($clauses['likeClauses'])) {
            $operationArray = array_merge($operationArray, array_keys($clauses['likeClauses']));
        }

        $operationArray = array_merge($operationArray, array_keys($sorts));

        if (isset($this->requiredIncludes)) {
            foreach ($this->requiredIncludes as $key => $values) {
                foreach ($values as $value) {
                    if (!in_array($value, $operationArray)) {
                        continue;
                    }
                    if (in_array($key, array_keys($includes))) {
                        continue;
                    }
                    $includes[$key] = $this->supportedIncludes[$key];
                }
            }
        }
    }

    # sends the clause array to different functions to be parsed
    protected function addWhereClauses(&$query, $clauseArray)
    {
        if (isset($clauseArray['likeClauses'])) {
            $this->parseLikeClauses($query, $clauseArray['likeClauses']);
        }

        if (isset($clauseArray['matchClauses'])) {
            $this->parseMatchClauses($query, $clauseArray['matchClauses']);
        }
    }

    # Parses match clauses
    protected function parseMatchClauses(&$query, $clauses)
    {
        foreach ($clauses as $key => $value) {
            $query->where($key, '=', $value);
        }
    }

    # Parse like clauses
    protected function parseLikeClauses(&$query, $clauses)
    {
        foreach ($clauses as $key => $value) {
            $term = str_replace(['+',','], ' ', $value);
            if ($key == 'keywords') {
                $keywords = explode(' ', $term);
                foreach ($keywords as $keyword) {
                    $query->where($key, 'like', '%' . $keyword . '%');
                }
                continue;
            }
            $query->where($key, 'like', '%' . $term . '%');
        }
    }

    # Adds order bys to the query
    protected function addOrderBys(&$query, $sorts)
    {
        foreach ($sorts as $key=>$value) {
            $sort = explode(' ', $key);
            $query->orderBy($sort[0], $sort[1]);
        }
    }

    # Add a limit to retrieved records
    protected function addLimit(&$query, $limit)
    {
        if (!$this->hasMaxRecords) {
            return;
        }

        $query->limit($limit);
    }

    # Add an offset to retrieved records
    protected function addOffset(&$query, $limit, $offsetMultiplier)
    {
        if (!$this->hasMaxRecords) {
            return;
        }

        $offset = $limit * $offsetMultiplier;
        $query->offset($offset);
    }
}
