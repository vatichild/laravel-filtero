<?php

namespace Vati\Filtero;

use Carbon\Carbon;
use Carbon\Exceptions\InvalidFormatException;
use DB;
use Illuminate\Support\Arr;

trait FilterTrait
{

    /**
     * Use properties below in your model for search and filter
     * 'recipient' and 'currency' are related models
     * Search Example: protected array $searchable = ['status', ['recipient' => ['CONCAT_WS(" ",first_name, last_name)'], 'currency'=>['code']]];
     * Filter Example: protected array $filterable = ['status', 'currency_id', 'provider_transaction_id', ['recipient' => ['country_id', 'city', 'phone', 'email']]]
     * Example query in repository or controller = $payment->with(['recipient'])->search()->filter()->sort()->paginate($request->per_page ?? 10);
     */

    /**
     * Scope a query to apply filter conditions based on request input.
     *
     * This method iterates over the `filterable` property, applying filter conditions
     * to the query. If an item in `filterable` is an array, it calls `filterRelations`
     * to apply filter conditions based on related models. Otherwise, it applies a direct
     * filter condition.
     *
     * @param $query
     * @param $request
     * @return void
     */
    public function scopeFilter($query, $request = null): void
    {
        $request = $request ?: request();

        $query->where(function ($q) use ($request) {
            foreach ($this->filterable as $item) {
                if (is_array($item)) {
                    $this->filterRelations($item, $q, $request);
                } else {
                    $this->applyFilter($request, $item, $q);
                }
            }
        });
    }

    /**
     * Scope a query to apply search conditions based on request input.
     *
     * This method checks if the request contains a search key, and if so, it applies
     * search conditions to the query. It iterates over the `searchable` property,
     * and if an item is an array, it calls `searchRelation` to handle related models.
     * Otherwise, it applies a direct search condition using `searchQuery`
     * @param $query
     * @param $request
     * @return void
     */
    public function scopeSearch($query, $request = null): void
    {
        $request = $request ?: request();

        if ($request->has(config('filtero.search_key'))) {
            $query->where(function ($q) use ($request) {
                foreach ($this->searchable as $item) {
                    if (is_array($item)) {
                        $this->searchRelation($item, $q, $request);
                    } else {
                        $this->searchQuery($q, $item, $request->input(config('filtero.search_key')));
                    }
                }
            });
        }
    }

    /**
     *  Scope a query to apply sorting based on request input.
     *
     *  This method reads the sort key from the request input, determines the
     *  sorting direction (ascending or descending), and applies the sorting
     *  to the query. It supports sorting by related models through dot notation.
     * @param $query
     * @param $request
     * @return void
     */
    public function scopeSort($query, $request = null): void
    {
        $request = $request ?: request();

        $sortInput = $request->input(config('filtero.sort_key'));
        if (isset($sortInput[0])) {
            $direction = $sortInput[0] == '-' ? 'DESC' : 'ASC';
            $sortKey = ltrim($request->input(config('filtero.sort_key')), '-');
            $relational = explode('.', $sortKey);
            if (in_array($sortKey, $this->sortable)) {
                if (count($relational) > 1) {
                    $this->sortRelation($relational, $query, $direction);
                } else {
                    if (!$this->sortWithSummedColumns($sortKey, $query, $direction)) {
                        $this->where($this->getTable() . '.' . $sortKey, $direction);
                    };
                }
            }
        }
    }


    /**
     *  Apply a search condition to the query for a specific column and value.
     *
     *  This method adds a where condition to the query, searching for a value
     *  in a specified column using a case-insensitive comparison. It uses a
     *  raw SQL query to perform a LIKE search.
     *
     * @param $query
     * @param $column
     * @param $value
     * @return mixed
     */

    private function searchQuery($query, $column, $value): mixed
    {
        $value = addslashes(strtolower($value));
        return $query->whereRaw('LOWER(' . $column . ') LIKE ?', ["%$value%"]);
    }

    /**
     * Apply a filter condition to the query for a specific item based on request input.
     *
     * This method checks if the request has a value for the specified item and
     * applies a where condition to the query with that value.
     *
     * @param $request
     * @param $item
     * @param $query
     * @return void
     */
    private function applyFilter($request, $item, $query): void
    {
        if ($request->has($item)) {
            $query->where($item, $request->input($item));
        }

        $this->applyRanges($request, $item, $query);
    }

    /**
     * Apply filter conditions to a query based on relations.
     *
     * This method iterates over the provided relations and their columns,
     * applying filter conditions to the query. It uses the `whereHas` method
     * to add conditions for each related model, checking if the request
     * contains specific filters and applying them.
     *
     * @param array $item An associative array where keys are relation names and values are arrays of columns to filter.
     * @param $query
     * @param $request
     * @return void
     */
    private function filterRelations(array $item, $query, $request): void
    {
        foreach ($item as $relation => $columns) {
            $query->whereHas($relation, function ($q) use ($relation, $columns, $request) {
                foreach ($columns as $column) {
                    $columnPath = $relation . '.' . $column;
                    if ($this->$relation()) {
                        $tableColumn = $this->$relation()->getModel()->getTable() . '.' . $column;
                        $this->applyRelationRanges($request, $tableColumn, $columnPath, $q);
                    }
                    if ($request->has($columnPath)) {
                        $q->where($column, $request->input($columnPath));
                    }
                }
            });
        }
    }


    /**
     * Apply a search filter to a query based on relations.
     *
     * This method iterates over the provided relations and their columns,
     * applying a search filter to the query. It uses the `orWhereHas` method
     * to add conditions for each related model, calling the `searchQuery`
     * method to apply the specific search condition.
     *
     * @param array $item An associative array where keys are relation names and values are arrays of columns to search.
     * @param $query
     * @param $request
     * @return void
     */
    private function searchRelation(array $item, $query, $request): void
    {
        foreach ($item as $table => $columns) {
            foreach ($columns as $column) {
                $query->orWhereHas($table, function ($q) use ($column, $request) {
                    return $this->searchQuery($q, $column, $request->input(config('filtero.search_key')));
                });
            }
        }
    }

    /**
     *  Apply sorting to a query based on a relation.
     *
     *  This method joins the related table to the main query and applies
     *  sorting based on the specified column in the related table. It is
     *  used when the sort key involves a related model.
     *
     * @param array $relational
     * @param $query
     * @param string $direction
     * @return void
     */
    private function sortRelation(array $relational, $query, string $direction): void
    {
        [$relation, $column] = $relational;
        $relationInstance = $this->$relation();
        if ($relationInstance) {
            $foreignKey = $this->$relation()->getForeignKeyName();
            $ownerKey = $this->$relation()->getOwnerKeyName();
            $relationTable = $this->$relation()->getModel()?->getTable();
            if ($relationTable && $foreignKey && $ownerKey) {
                $query->join($relationTable, $this->getTable() . '.' . $foreignKey, '=', $relationTable . '.' . $ownerKey);
                $query->orderBy($relationTable . '.' . $column, $direction);
            }
        }
    }

    /**
     * @param $request
     * @param $item
     * @param $query
     * @return void
     */
    private function applyRanges($request, $item, $query): void
    {
        $ranges = $this->getRanges($request);

        if ($ranges) {
            foreach ($ranges as $key => $range) {
                if ($key == $item) {
                    $this->rangeQuery($query, $this->getTable() . '.' . $key, $range);
                }
            }
        }
    }

    /**
     * Get the ranges from the request.
     *
     * @param $request
     * @return array|null
     */
    private function getRanges($request): ?array
    {
        $ranges = $request->input(config('filtero.range_key'));
        return $ranges && count($ranges) ? $ranges : null;
    }

    /**
     * Apply range filters based on relation and column.
     *
     * @param $request
     * @param string $tableColumn
     * @param string $columnPath
     * @param $query
     * @return void
     */
    private function applyRelationRanges($request, string $tableColumn, string $columnPath, $query): void
    {
        $ranges = $this->getRanges($request);
        if ($ranges && Arr::has($ranges, $columnPath)) {
            $range = $request->input(config('filtero.range_key') . '.' . $columnPath);
            $this->rangeQuery($query, $tableColumn, $range);
        }
    }

    /**
     * Format the given date string to include time, defaulting to start or end of the day.
     *
     * @param string $string The date string to be formatted.
     * @param bool $max Whether to set the time to the end of the day (23:59:59). Defaults to false, setting time to the start of the day (00:00:00).
     * @return string The formatted date string with the appropriate time suffix, or the original string if parsing fails.
     */
    private function rangeFormat(string $string, bool $max = false): string
    {
        try {
            $suffix = $max ? '23:59:59' : '00:00:00';
            return Carbon::parse($string)->format('Y-m-d') . ' ' . $suffix;
        } catch (InvalidFormatException $e) {
            return $string;
        }
    }

    /**
     * Adds range-based filtering to a query based on provided minimum and maximum values.
     *
     * This method modifies the given query object to add constraints on a specified column based
     * on the 'min' and 'max' values provided in the range array. It formats the range values using
     * the `rangeFormat` method and includes the following logic:
     *
     * - If only 'min' is provided, it adds a `where` clause with a greater-than (or greater-than-or-equal) condition.
     * - If only 'max' is provided, it adds a `where` clause with a less-than (or less-than-or-equal) condition.
     * - If both 'min' and 'max' are provided, it adds a `whereBetween` clause to constrain the column to the range.
     *
     * The comparison operators can include equality based on the `includeEqualInRange` property.
     *
     * @param $query
     * @param string $tableColumn
     * @param array $range
     *
     * @return void
     */
    private function rangeQuery($query, string $tableColumn, array $range): void
    {
        $min = isset($range['min']) ? $this->rangeFormat($range['min']) : null;
        $max = isset($range['max']) ? $this->rangeFormat($range['max'], true) : null;

        if ($min && !$max) {
            $this->applyMinRange($query, $tableColumn, $min);
        } elseif ($max && !$min) {
            $this->applyMaxRange($query, $tableColumn, $max);
        } elseif ($min && $max) {
            $query->whereBetween($tableColumn, [$min, $max]);
        }
    }

    /**
     * Apply minimum range filter to the query.
     *
     * @param $query
     * @param string $tableColumn
     * @param mixed $min
     *
     * @return void
     */
    private function applyMinRange($query, string $tableColumn, mixed $min): void
    {
        $operator = config('filtero.include_equal_in_range_filter') ? '>=' : '>';
        $query->where($tableColumn, $operator, $min);
    }

    /**
     * Apply maximum range filter to the query.
     *
     * @param $query
     * @param string $tableColumn
     * @param mixed $max
     *
     * @return void
     */
    private function applyMaxRange($query, string $tableColumn, mixed $max): void
    {
        $operator = config('filtero.include_equal_in_range_filter') ? '<=' : '<';
        $query->where($tableColumn, $operator, $max);
    }

    /**
     *  Sort a query by the sum of multiple columns.
     *
     *  This method takes a sort key string, splits it into column names,
     *  and sorts the query based on the sum of these columns in the specified direction
     *
     * @param string $sortKey
     * @param $query
     * @param string $direction
     * @return bool
     */
    public function sortWithSummedColumns(string $sortKey, $query, string $direction): bool
    {
        $sumArray = explode('{sum}', addslashes($sortKey));
        if (count($sumArray)) {
            $sumOfColumns = join('+', array_map(function ($column) {
                return $this->getTable() . '.' . $column;
            }, $sumArray));
            $query->orderBy(DB::raw("($sumOfColumns)"), $direction);
            return true;
        }
        return false;
    }
}
