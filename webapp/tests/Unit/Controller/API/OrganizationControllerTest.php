<?php declare(strict_types=1);

namespace App\Tests\Unit\Controller\API;

class OrganizationControllerTest extends BaseTest
{
    protected $apiEndpoint = 'organizations';

    protected $expectedObjects = [
        '1' => [
            'icpc_id'     => '1',
            'shortname'   => 'UU',
            'id'          => '1',
            'name'        => 'UU',
            'formal_name' => 'Utrecht University',
            'country'     => 'NLD'
        ],
    ];

    protected $expectedAbsent = ['4242', 'nonexistent'];
}
