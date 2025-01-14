<?php

namespace Tests\Integration\Schema\Directives;

use Tests\DBTestCase;
use Tests\Utils\Models\User;
use Tests\Utils\Models\Company;

class FindDirectiveTest extends DBTestCase
{
    public function testReturnsSingleUser(): void
    {
        $userA = factory(User::class)->create(['name' => 'A']);
        $userB = factory(User::class)->create(['name' => 'B']);
        $userC = factory(User::class)->create(['name' => 'C']);

        $this->schema = '
        type User {
            id: ID!
            name: String!
        }
        
        type Query {
            user(id: ID @eq): User @find(model: "User")
        }
        ';

        $this->graphQL("
        {
            user(id:{$userB->id}) {
                name
            }
        }
        ")->assertJsonFragment([
            'user' => [
                'name' => 'B',
            ],
        ]);
    }

    public function testDefaultsToFieldTypeIfNoModelIsSupplied(): void
    {
        $userA = factory(User::class)->create(['name' => 'A']);
        $userB = factory(User::class)->create(['name' => 'B']);

        $this->schema = '
        type User {
            id: ID!
            name: String!
        }
        
        type Query {
            user(id: ID @eq): User @find
        }
        ';

        $this->graphQL("
        {
            user(id:{$userA->id}) {
                name
            }
        }
        ")->assertJsonFragment([
            'name' => 'A',
        ]);
    }

    public function testCannotFetchIfMultipleModelsMatch(): void
    {
        factory(User::class)->create(['name' => 'A']);
        factory(User::class)->create(['name' => 'A']);
        factory(User::class)->create(['name' => 'B']);

        $this->schema = '
        type User {
            id: ID!
            name: String!
        }
        
        type Query {
            user(name: String @eq): User @find(model: "User")
        }
        ';

        $this->graphQL('
        {
            user(name: "A") {
                name
            }
        }
        ')->assertJsonCount(1, 'errors');
    }

    public function testCanUseScopes(): void
    {
        $companyA = factory(Company::class)->create(['name' => 'CompanyA']);
        $companyB = factory(Company::class)->create(['name' => 'CompanyB']);
        $userA = factory(User::class)->create(['name' => 'A', 'company_id' => $companyA->id]);
        $userB = factory(User::class)->create(['name' => 'A', 'company_id' => $companyB->id]);
        $userC = factory(User::class)->create(['name' => 'B', 'company_id' => $companyA->id]);

        $this->schema = '
        type Company {
            name: String!
        }
        
        type User {
            id: ID!
            name: String!
        }
        
        type Query {
            user(name: String @eq, company: String!): User @find(model: "User" scopes: [companyName])
        }
        ';

        $this->graphQL('
        {
            user(name: "A" company: "CompanyA") {
                id
                name
            }
        }
        ')->assertJson([
            'data' => [
                'user' => [
                    'id' => $userA->id,
                    'name' => 'A',
                ],
            ],
        ]);
    }

    public function testReturnsAnEmptyObjectWhenTheModelIsNotFound(): void
    {
        $this->schema = '
        type User {
            id: ID!
            name: String!
        }
        
        type Query {
            user(name: String @eq): User @find(model: "User")
        }
        ';

        $this->graphQL('
        {
            user(name: "A") {
                id
                name
            }
        }
        ')->assertJson([
            'data' => [
                'user' => null,
            ],
        ])->assertStatus(200);
    }
}
