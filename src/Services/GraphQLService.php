<?php

namespace markhuot\CraftQL\Services;

use Craft;
use GraphQL\GraphQL;
use GraphQL\Error\Debug;
use GraphQL\Language\AST\InterfaceTypeDefinitionNode;
use GraphQL\Language\Parser;
use GraphQL\Type\Schema;
use GraphQL\Utils\AST;
use GraphQL\Utils\BuildSchema;
use GraphQL\Utils\SchemaPrinter;
use GraphQL\Validator\DocumentValidator;
use GraphQL\Validator\Rules\QueryComplexity;
use GraphQL\Validator\Rules\QueryDepth;
use markhuot\CraftQL\CraftQL;
use markhuot\CraftQL\Events\AlterQuerySchema;
use markhuot\CraftQL\Helpers\StringHelper;
use markhuot\CraftQL\TypeRegistry;
use markhuot\CraftQL\Types\DynamicEntryType;
use markhuot\CraftQL\Types\EntryConnection;
use markhuot\CraftQL\Types\EntryInterface;
use markhuot\CraftQL\Types\Query;
use markhuot\CraftQL\Types\Site;
use markhuot\CraftQL\Types\Volume;
use yii\base\Component;

class GraphQLService extends Component {

    private $schema;
    private $volumes;
    private $categoryGroups;
    private $tagGroups;
    private $entryTypes;
    private $sections;
    private $globals;

    function __construct(
        \markhuot\CraftQL\Repositories\Volumes $volumes,
        \markhuot\CraftQL\Repositories\CategoryGroup $categoryGroups,
        \markhuot\CraftQL\Repositories\TagGroup $tagGroups,
        \markhuot\CraftQL\Repositories\EntryType $entryTypes,
        \markhuot\CraftQL\Repositories\Section $sections,
        \markhuot\CraftQL\Repositories\Globals $globals
    ) {
        $this->volumes = $volumes;
        $this->categoryGroups = $categoryGroups;
        $this->tagGroups = $tagGroups;
        $this->entryTypes = $entryTypes;
        $this->sections = $sections;
        $this->globals = $globals;
    }

    /**
     * Bootstrap the schema
     *
     * @return void
     */
    function bootstrap() {
        $this->volumes->load();
        $this->categoryGroups->load();
        $this->tagGroups->load();
        $this->entryTypes->load();
        $this->sections->load();
        $this->globals->load();

        $maxQueryDepth = CraftQL::getInstance()->getSettings()->maxQueryDepth;
        if ($maxQueryDepth !== false) {
            $rule = new QueryDepth($maxQueryDepth);
            DocumentValidator::addRule($rule);
        }

        $maxQueryComplexity = CraftQL::getInstance()->getSettings()->maxQueryComplexity;
        if ($maxQueryComplexity !== false) {
            $rule = new QueryComplexity($maxQueryComplexity);
            DocumentValidator::addRule($rule);
        }
    }

    function getSchema($token) {
        $request = new \markhuot\CraftQL\Request($token);
        $request->addCategoryGroups(new \markhuot\CraftQL\Factories\CategoryGroup($this->categoryGroups, $request));
        $request->addEntryTypes(new \markhuot\CraftQL\Factories\EntryType($this->entryTypes, $request));
        $request->addVolumes(new \markhuot\CraftQL\Factories\Volume($this->volumes, $request));
        $request->addSections(new \markhuot\CraftQL\Factories\Section($this->sections, $request));
        $request->addTagGroups(new \markhuot\CraftQL\Factories\TagGroup($this->tagGroups, $request));
        $request->addGlobals(new \markhuot\CraftQL\Factories\Globals($this->globals, $request));

        $registry = new TypeRegistry($request);
        $registry->registerNamespace('\\markhuot\\CraftQL\\Types');

        // if ($schemaText = Craft::$app->cache->get('foo')) {
        //     $typeConfigDecorator = function ($type) use ($registry) {
        //         if (get_class($type['astNode']) == InterfaceTypeDefinitionNode::class) {
        //             $fqen = $registry->getClassForName($type['name']);
        //             $type['resolveType'] = function ($source) use ($fqen) {
        //                 return $fqen::craftQLResolveType($source);
        //             };
        //         }
        //         return $type;
        //     };
        //     $schema = BuildSchema::build(AST::fromArray(unserialize($schemaText)), $typeConfigDecorator);
        //     return [$request, $schema];
        // }

        foreach ($this->entryTypes->all() as $type) {
            $name = StringHelper::graphQLNameForEntryType($type);
            $registry->add($name, DynamicEntryType::class, $type);
        }

        $schemaConfig = [];

        $query = new \markhuot\CraftQL\Types\Query($request);

        $event = new AlterQuerySchema;
        $event->query = $query;
        $query->trigger(AlterQuerySchema::EVENT, $event);

        $schemaConfig['query'] = $query->getRawGraphQLObject();

        $schemaConfig['typeLoader'] = function ($name) use ($registry) {
            // Craft::beginProfile('load type '.$name, 'craftqlTypeLoader');
            $foo = $registry->get($name);
            // Craft::endProfile('load type '.$name, 'craftqlTypeLoader');
            return $foo;
        };

        // $schemaConfig['types'] = function () use ($request, $query) {
        //     return array_merge(
        //         // array_map(function ($section) {
        //         //     return $section->getRawGraphQLObject();
        //         // }, $request->sections()->all()),
        //
        //         array_map(function ($volume) {
        //             return $volume->getRawGraphQLObject();
        //         }, $request->volumes()->all()),
        //
        //         array_map(function ($categoryGroup) {
        //             return $categoryGroup->getRawGraphQLObject();
        //         }, $request->categoryGroups()->all()),
        //
        //         array_map(function ($tagGroup) {
        //             return $tagGroup->getRawGraphQLObject();
        //         }, $request->tagGroups()->all()),
        //
        //         array_map(function ($entryType) {
        //             return $entryType->getRawGraphQLObject();
        //         }, $request->entryTypes()->all()),
        //
        //         [\markhuot\CraftQL\Directives\Date::dateFormatTypesEnum()],
        //
        //         $query->getConcreteTypes()
        //     );
        // };

        $schemaConfig['directives'] = [
            \markhuot\CraftQL\Directives\Date::directive(),
        ];

        // $mutation = (new \markhuot\CraftQL\Types\Mutation($request))->getRawGraphQLObject();
        // $schemaConfig['mutation'] = $mutation;

        $schemaConfig['types'] = $registry->all();

        $schema = new Schema($schemaConfig);

        if (Craft::$app->config->general->devMode) {
            // $schema->assertValid();
        }

        $schemaText = SchemaPrinter::doPrint($schema);
        $schemaText = serialize(AST::toArray(Parser::parse($schemaText)));
        // header('content-type: text/plain');
        // echo $schemaText;
        // die;
        Craft::$app->cache->set('foo', $schemaText);

        return [$request, $schema];
    }

    function execute($request, $schema, $input, $variables = []) {
        $debug = Craft::$app->config->getGeneral()->devMode ? Debug::INCLUDE_DEBUG_MESSAGE | Debug::RETHROW_INTERNAL_EXCEPTIONS : null;
        return GraphQL::executeQuery($schema, $input, new Query($request), null, $variables, '', function ($source, $args, $context, $info) use ($request) {
            $fieldName = $info->fieldName;

            // if ($fieldName == 'dateCreated') {
            //     var_dump($source);
            //     die;
            // }

            $property = null;

            if (is_object($source)) {

                // Because we can't modify internal classes we dynamically attach
                // behaviors here based on our behavior mappings
                if (is_subclass_of($source, Component::class)) {
                    $behaviors = require(CraftQL::PATH() . 'behaviors.php');

                    // $sourceClassName = get_class($source);
                    // if (in_array($sourceClassName, array_keys($behaviors))) {
                    foreach ($behaviors as $foo => $bar) {
                        if (!is_a($source, $foo) && !is_subclass_of($source, $foo)) {
                            continue;
                        }

                        foreach ($bar as $behavior) {
                            if (!$source->getBehavior($behavior)) {
                                $source->attachBehavior($behavior, $behavior);
                            }
                        }
                    }
                }

                if (method_exists($source, $method='getCraftQL'.ucfirst($fieldName))) {
                    $property = $source->{$method}($request, $source, $args, $context, $info);
                }
                else if (method_exists($source, 'hasMethod') && $source->hasMethod($method='getCraftQL'.ucfirst($fieldName))) {
                    $property = $source->{$method}($request, $source, $args, $context, $info);
                }
                else if (method_exists($source, $method='get'.ucfirst($fieldName))) {
                    // we're not passing $source, $args, $context, $info here because
                    // we don't have confidence that the getField method is GraphQL
                    // aware and it could be expecting a completely different set of
                    // parameters.
                    $property = $source->{$method}();
                }
                else if (isset($source->{$fieldName})) {
                    $property = $source->{$fieldName};
                }
            }

            if ($property == null && (is_array($source) || $source instanceof \ArrayAccess)) {
                if (isset($source[$fieldName])) {
                    $property = $source[$fieldName];
                }
            }

            // downcast things we're able to
            switch (get_class($info->returnType)) {
                case \GraphQL\Type\Definition\StringType::class: $property = (string)$property;
            }

            return $property instanceof \Closure ? $property($source, $args, $context) : $property;
        })->toArray($debug);
    }

}
