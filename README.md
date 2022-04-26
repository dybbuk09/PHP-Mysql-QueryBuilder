## PHP-Mysql QueryBuilder
Query builder package to implement database queries easily in core PHP.

## Requirements
- PHP 7.0 or higher
- Composer for installation

## Installation
composer require hraw/dbqb

## Implementation

Example: filename -> index.php
```
<?php

require_once __DIR__.'/vendor/autoload.php';

use Hraw\DBQB\DB;

//Connect to database
DB::connect([
    'name'      =>  'default',
    'driver'    =>  'mysql',
    'host'      =>  'localhost',
    'database'  =>  'test',
    'username'  =>  'username',
    'password'  =>  'password',
]);

//Fetch all the data from table
DB::all('tableName');

//Fetch data with conditions
DB::table('users')->where('id', '>', 1)->get();

//Fetch data with array of conditions
DB::table('users')->where([
                        'salary'    =>  10000,
                        'profile'   =>  'developer'   
                   ])
                   ->get();

//Fetch only single record
DB::table('users')->where('id', '>', 1)->first();

//Print sql query
DB::table('users')->where('id', '>', 1)->toSql();

//Deleting a record
DB::table('users')->where('id', 10)->delete();

//Updating a record
DB::table('users')->where('role_id', 1)
    ->update([
        'designation'   =>  'Admin'
    ]);
    
//Inserting a record
DB::table('users')
    ->insert([
        'fname' =>  'John',
        'lname' =>  'Doe'
    ]);
    
//Inserting bulk records
DB::table('users')
    ->batchInsert([
        [
            'fname' =>  'John',
            'lname' =>  'Doe'
        ],
        [
            'fname' =>  'peter',
            'lname' =>  'parker'
        ]
    ]);
    
Note:- batchInsert function does not return id of the inserted rows however insert function returns the id of the inserted row.
    
//Nested conditions
DB::table('users')->where('name', 'like', '%jo%')
                  ->where(function($query){
                    $query->where('age', '>', 18)
                          ->orWhere('state_code', 'UK');
                  })
                  ->first();
                  
//Group By, Order By and Limit
DB::table('users')->where('name', 'black tshirt')
                  ->groupBy('size', 'sku')
                  ->orderBy('id', 'desc')
                  ->limit(20)
                  ->get();
                  
//Fetch data using union and unionAll
DB::table('users')
     ->select('id')
     ->where('fname', 'john')
     ->union(
         DB::table('posts')
             ->select('id')
             ->where('id', 1)
     )
     ->get();
     
DB::table('users')
     ->select('id')
     ->where('fname', 'john')
     ->unionAll(
         DB::table('posts')
             ->select('id')
             ->where('id', 1)
     )
     ->get();
     
//Fetch data using joins
DB::table('users')
    ->join('orders', 'users.id', '=', 'orders.user_id')
    ->select('users.*', 'orders.*')
    ->get();
    
Note:- join method uses inner join for joining the tables.
        Other supported methods:
        1) leftJoin, 2) rightJoin, 3) outerJoin
        
DB::table('users')
    ->leftJoin('posts', 'users.id', '=', 'posts.user_id')
    ->get();
 
DB::table('users')
    ->rightJoin('posts', 'users.id', '=', 'posts.user_id')
    ->get();
```

## Transactions
```
DB::beginTransaction();
try {
    DB::table('users')->where('id', '<', 10)->delete();
    DB::commit();
} catch (\Exception $e) {
    DB::rollBack();
}
```

## Multiple database connections
```
DB::connection('connection1Name')->all('tableName');

DB::connection('connection2Name')->all('tableName');
```

## Supported methods
* where('columnName', 'operator', 'value') or where([$keyValues]) //operator is optional
* orWhere('columnName', 'operator', 'value') //operator is optional
* whereIn('columnName', 'values') //values must an array
* orWhereIn('columnName', 'values') //values must an array
* whereNotIn('columnName', 'values') //values must an array
* orWhereNotIn('columnName', 'values') //values must an array
* whereBetween('columnName', [val1, val2])
* whereNotBetween('columnName', [val1, val2])
* orWhereBetween('columnName', [val1, val2])
* orWhereNotBetween('columnName', [val1, val2])
* whereNull('columnName')
* orWhereNull('columnName')
* whereNotNull('columnName')
* orWhereNotNull('columnName')
* orderBy('columnName', type) // type can be ASC or DESC
* groupBy(column1, column2)
* limit(value)
* offset(value)
* raw(query, placeholders) //you can run raw queries through this query builder with or without prepare statements, for prepare statements all you have to do is pass array of values of in placeholders parameter(optional parameter).
* count('columnName') //optional
* min('columnName')
* max('columnName')
* avg('columnName')
* sum('columnName')
* insert([values]) //Insert single row in table, values must be an associative array
* batchInsert([values]) //Insert multiple records in table, values must array of arrays
* delete() //Delete record from database
* update([values]) //values must be an associative array
* having (Note:- All having methods are same as where methods)


## Run Server
php -S localhost:8000