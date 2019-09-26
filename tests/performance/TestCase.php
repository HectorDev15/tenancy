<?php

declare(strict_types=1);

/*
 * This file is part of the tenancy/tenancy package.
 *
 * Copyright Tenancy for Laravel & Daniël Klabbers <daniel@klabbers.email>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @see https://tenancy.dev
 * @see https://github.com/tenancy
 */

namespace Tenancy\Tests\Performance;

use Blackfire\Client;
use Blackfire\ClientConfiguration;
use Illuminate\Database\Schema\Blueprint;
use Tenancy\Affects\Filesystems\Provider as Filesystems;
use Tenancy\Affects\Models\Provider as Models;
use Tenancy\Affects\Views\Provider as Views;
use Tenancy\Identification\Contracts\ResolvesTenants;
use Tenancy\Identification\Drivers\Http\Providers\IdentificationProvider as HttpIdentification;
use Tenancy\Testing\TestCase as Test;
use Tenancy\Tests\Identification\Http\Mocks\Hostname;

abstract class TestCase extends Test
{
    protected $additionalProviders = [
        HttpIdentification::class,
        Filesystems::class,
        Models::class,
        Views::class,
    ];
    protected $additionalMocks = [
        __DIR__.'/../unit/Identification/Http/Mocks/factories/'
    ];

    /** @var Client */
    protected $blackfire;

    protected function beforeBoot()
    {
        $this->blackfire = new Client(new ClientConfiguration(
            env('BLACKFIRE_CLIENT_ID'),
            env('BLACKFIRE_CLIENT_TOKEN')
        ));
    }

    protected function compare(callable $test)
    {
        $probe = $this->blackfire->createProbe();

        // No identification.
        $test();

        $cleanProfile = $this->blackfire->endProbe($probe);

        // Identify and re-run.
        $probe = $this->blackfire->createProbe();

        /** @var ResolvesTenants $resolver */
        $resolver = resolve(ResolvesTenants::class);
        $resolver->addModel(Hostname::class);

        $this->createSystemTable('hostnames', function (Blueprint $table) {
            $table->increments('id');
            $table->string('fqdn');
            $table->timestamps();
        });

        $hostname = factory(Hostname::class)->create();

        $test($hostname);

        $profile = $this->blackfire->endProbe($probe);

        $this->assertLessThan($cleanProfile->getMainCost()->getCpu(), $profile->getMainCost()->getCpu());
    }
}
