# Filtero

Filtero is a Laravel package that provides a convenient way to filter, search, and sort data from models and their
relationships.
You only have to provide request query params properly, and Filtero will handle the rest for you.

## Installation

You can install the package via composer:

```bash
composer require vati/filtero
```

## Usage

1. **Include FilterTrait in your Model**: Use the provided `FilterTrait` trait in your model to enable filtering,
   searching, and sorting capabilities.

    ```php
    use Vati\Filtero\FilterTrait;

    class YourModel extends Model
    {
        use FilterTrait;

        /**
         * The columns that are searchable.
         *
         * @var array
         */
        protected array $searchable = [
            'status',
            [
                'recipient' => [
                    'CONCAT_WS(" ", first_name, last_name)'
                ], 
                'currency' => [
                    'code'
                ]
            ],
        ];

        /**
         * The columns that are filterable.
         *
         * @var array
         */
        protected array $filterable = [
            'status',
            'currency_id',
            'provider_transaction_id',
            [
                'recipient' => [
                    'country_id', 
                    'city', 
                    'phone', 
                    'email'
                ]
            ],
        ];
   
       /**
        * The columns that are sortable.
        *
        * @var array
        */
        protected array $sortable = [
            'id',
            'user_id',
            'recipient_id',
            'recipient.first_name', //relation sort
            'payout_id',
            'estimated_provider_fee{sum}estimated_platform_fee', // sum multiple columns, order must be same in http_query
            'payment_amount',
            'total_amount',
            'currency_id',
            'status',
            'reference',
            'created_at'
        ];
    }
    ```

2. **Use in Repository or Controller**: Utilize the filtering, searching, and sorting capabilities in your repository or
   controller.

    ```php
    $payments = YourModel::with(['recipient'])->search()->filter()->sort()->paginate($request->per_page ?? 10);
    ```

## Example Query

An example query in your repository or controller would look like:

```php
$payments = YourModel::with(['recipient'])
            ->search()
            ->filter()
            ->sort()
            ->paginate($request->per_page ?? 10);
```

If you only want to perform a search without filtering or sorting, you can use ```$payment->search()```.

### Api Endpoint Realistic Example

Here's a realistic example of how you can use Filtero in your API endpoints:

```plaintext
/payment/payments?search=example@example.com&sort=recipient.first_name&status=completed&currency_id=3&currency[code]=CHF&range[created_at][min]=2024-05-22&range[created_at][max]=2024-06-22
```

## Request Options

Your request can contain various options for filtering, searching, and sorting:

- **Search**: Use the `search` query parameter to perform a search. Example: `?search=example@example.com`.
-
    - To search multiple columns as one, like searching for "John Doe" across first_name and last_name, use the
      CONCAT_WS function in your searchable array.
- **Sort**: Use the `sort` query parameter to specify the sorting column. You can use dot notation for relational
  sorting. Example: `?sort=recipient.first_name`.
-
    - Sort by the attribute in descending order: `?sort=-created_at`
  
    - Let's say you want to sort the results based on the sum of `estimated_provider_fee` and `estimated_platform_fee`,
      in
      ascending order. Your request URL would look like this:

      ```
      GET /your/route?sort=estimated_provider_fee{sum}estimated_platform_fee
      ```

-
    - This will return the results sorted based on the sum of `estimated_provider_fee` and `estimated_platform_fee`.
-
    - Example query for sum: `?sort=estimated_provider_fee{sum}estimated_platform_fee`

- **Filter**: Use query parameters to filter data based on specific attributes.
-
    - For Example:
-
    -
        - To filter by the `city` attribute in the related `recipient` table: `?recipient[city]=New York`
-
    -
        - To filter by the `status` attribute in the main table: `?status=completed`

- **Range**: You can specify a range for date attributes using the `range` query parameter.
  Example: `?range[created_at][min]=2024-05-22&range[created_at][max]=2024-06-22`.

Of course! Here's the updated section regarding the range filter:

---

## Range Filter

The range filter allows you to specify a range for numeric attributes, in addition to date attributes. This feature
provides flexibility in filtering data based on various numeric criteria.

### Usage

To use the range filter:

- Specify the `min` and/or `max` values for the range of the attribute you want to filter.
- Include the range in the request using the `range` query parameter.

### Example

```php
// Example request URL
?range[price][min]=10&range[price][max]=100
```

In this example, `price` is a numeric attribute, and the query filters records where the price falls within the range of
10 to 100.


---

Feel free to let me know if there are any more adjustments or additions you'd like!

## Configuration

You can configure Filtero by modifying the `config/filtero.php` file. The configuration options include:

- `search_key`: The key used for search queries.
- `sort_key`: The key used for sorting queries.
- `range_key`: The key used for range queries.
- `include_equal_in_range_filter`: Option to include equal values in range filtering.

## Publishing Configuration

To publish the configuration file, run the following Artisan command:

```bash
php artisan vendor:publish --provider="Vati\Filtero\FilteroServiceProvider"
```

## Credits

- [Vati Child](https://github.com/vatichild)

## License

The Filtero package is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
