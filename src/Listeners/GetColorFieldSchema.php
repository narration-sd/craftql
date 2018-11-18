<?php

namespace markhuot\CraftQL\Listeners;

use craft\fields\data\ColorData;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Type\Definition\Type;

class GetColorFieldSchema
{
    /**
     * Handle the request for the schema
     *
     * @param \markhuot\CraftQL\Events\GetFieldSchema $event
     * @return void
     */
    function handle($event) {
        $event->handled = true;

        $event->schema->addField($event->sender)
            ->type(ColorData::class);

        $event->mutation->addStringArgument($event->sender);
    }
}