<?php

namespace markhuot\CraftQL\Behaviors;

use GraphQL\Type\Definition\ResolveInfo;
use markhuot\CraftQL\Request;
use yii\base\Behavior;

class Entry extends Behavior {

    /**
     * @TODO revisit if this is necessary, we're setting permissions on the $request->entries so that's good, but is it necessary since it's asking for children and we probably want to return all the children anyway
     */
    function getCraftQLChildren(Request $request, \craft\elements\Entry $entry, $args, $context, ResolveInfo $info) {
        return $request->entries($entry->{$info->fieldName}, $entry, $args, $context, $info);
    }

    function getCraftQLDateCreated(Request $request, \craft\elements\Entry $entry, $args, $context, ResolveInfo $info) {
        return (int)$entry->dateCreated->format('U');
    }

    function getCraftQLDateUpdated(Request $request, \craft\elements\Entry $entry, $args, $context, ResolveInfo $info) {
        return (int)$entry->dateUpdated->format('U');
    }

    function getCraftQLExpiryDate(Request $request, \craft\elements\Entry $entry, $args, $context, ResolveInfo $info) {
        return (int)$entry->expiryDate->format('U');
    }

    function getCraftQLPostDate(Request $request, \craft\elements\Entry $entry, $args, $context, ResolveInfo $info) {
        return (int)$entry->postDate->format('U');
    }

}