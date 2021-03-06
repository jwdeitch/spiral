<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 * @copyright �2009-2015
 */
namespace Spiral\Reactor\Generators;

use Doctrine\Common\Inflector\Inflector;
use Spiral\Core\Controller;
use Spiral\Http\Exceptions\ClientException;
use Spiral\Reactor\Generators\Prototypes\AbstractService;

/**
 * Generates controller classes.
 */
class ControllerGenerator extends AbstractService
{
    /**
     * {@inheritdoc}
     */
    protected function generate()
    {
        $this->file->addUse(Controller::class);
        $this->class->setParent('Controller');
    }

    /**
     * Generate GRUD methods using data entity service.
     *
     * @param string $name
     * @param string $serviceClass
     * @param string $request
     * @param string $requestClass
     */
    public function createCRUD($name, $serviceClass, $request = '', $requestClass = '')
    {
        $this->file->addUse(ClientException::class);

        $plural = Inflector::pluralize($name);
        $this->addDependency($name, $serviceClass);
        $this->class->property('defaultAction', "@var string")->setDefault(true, 'retrieve');

        //Let's generate some fun!
        $retrieve = $this->class->method('retrieve')->setComment([
            "Retrieve all entities selected from {$serviceClass} and render them using view '{$plural}/list'.",
            "",
            "@return string"
        ]);

        //Let's include pagination
        $retrieve->setSource([
            "return \$this->views->render('{$plural}/list', [",
            "    'list' => \$this->{$plural}->find()->paginate(50)",
            "]);"
        ]);

        //Let's generate some fun!
        $show = $this->class->method('show')->setComment([
            "Fetch one entity from {$serviceClass} and render it using view '{$plural}/show'.",
            "",
            "@param string \$id",
            "@return string"
        ]);

        $show->parameter('id');
        $show->setSource([
            "if (empty(\$entity = \$this->{$plural}->findByPK(\$id))) {",
            "    throw new ClientException(ClientException::NOT_FOUND);",
            "}",
            "",
            "return \$this->views->render('{$plural}/show', compact('entity'));"
        ]);

        //Let's generate some fun!
        $update = $this->class->method('update')->setComment([
            "Update existed entity using {$serviceClass}.",
            "",
            "@param string \$id",
        ]);

        //We are going to fetch entity id from route parameters
        $update->parameter('id');

        if (!empty($request)) {
            $this->file->addUse($requestClass);
            $reflection = new \ReflectionClass($requestClass);
            $update->parameter($request, $reflection->getShortName())->setType(
                $reflection->getShortName()
            );

            $update->setSource([
                "if (empty(\$entity = \$this->{$plural}->findByPK(\$id))) {",
                "    throw new ClientException(ClientException::NOT_FOUND);",
                "}",
                "",
                "if (!\${$request}->isValid()) {",
                "    return [",
                "        'status' => ClientException::BAD_DATA,",
                "        'errors' => \${$request}->getErrors()",
                "    ];",
                "}",
                "",
                "\$entity->setFields(\${$request});",
                "if (!\$this->{$plural}->save(\$entity, true, \$errors)) {",
                "    return [",
                "        'status' => ClientException::BAD_DATA,",
                "        'errors' => \$errors",
                "    ];",
                "}",
                "",
                "return ['status' => 204, 'message' => 'Updated'];"
            ]);
        } else {
            $update->setSource([
                "if (empty(\$entity = \$this->{$plural}->findByPK(\$id))) {",
                "    throw new ClientException(ClientException::NOT_FOUND);",
                "}",
                "",
                "\$entity->setFields(\$this->input->data);",
                "if (!\$this->{$plural}->save(\$entity, true, \$errors)) {",
                "    return [",
                "        'status' => ClientException::BAD_DATA,",
                "        'errors' => \$errors",
                "    ];",
                "}",
                "",
                "return ['status' => 204, 'message' => 'Updated'];"
            ]);
        }

        //Return JSON
        $update->setComment("@return array", true);

        //New entity creation
        $create = $this->class->method('create')->setComment([
            "Create new entity using {$serviceClass}.",
            ""
        ]);

        if (!empty($request)) {
            $this->file->addUse($requestClass);
            $reflection = new \ReflectionClass($requestClass);
            $create->parameter($request, $reflection->getShortName())->setType(
                $reflection->getShortName()
            );

            $create->setSource([
                "if (!\${$request}->isValid()) {",
                "    return [",
                "        'status' => ClientException::BAD_DATA,",
                "        'errors' => \${$request}->getErrors()",
                "    ];",
                "}",
                "",
                "\$entity = \$this->{$plural}->create(\${$request});",
                "if (!\$this->{$plural}->save(\$entity, true, \$errors)) {",
                "    return [",
                "        'status' => ClientException::BAD_DATA,",
                "        'errors' => \$errors",
                "    ];",
                "}",
                "",
                "return ['status' => 201, 'message' => 'Created', 'id' => \$entity->primaryKey()];"
            ]);
        } else {
            $create->setSource([
                "\$entity = \$this->{$plural}->create(\$this->input->data);",
                "if (!\$this->{$plural}->save(\$entity, true, \$errors)) {",
                "    return [",
                "        'status' => ClientException::BAD_DATA,",
                "        'errors' => \$errors",
                "    ];",
                "}",
                "",
                "return ['status' => 201, 'message' => 'Created', 'id' => \$entity->primaryKey()];"
            ]);
        }

        //Return JSON
        $create->setComment("@return array", true);

        //Let's generate some fun!
        $delete = $this->class->method('delete')->setComment([
            "Delete one entity using it's primary key and service {$serviceClass}. JSON will be returned.",
            "",
            "@param string \$id",
            "@return array"
        ]);

        $delete->parameter('id');
        $delete->setSource([
            "if (empty(\$entity = \$this->{$plural}->findByPK(\$id))) {",
            "    throw new ClientException(ClientException::NOT_FOUND);",
            "}",
            "",
            "if (!\$this->{$plural}->delete(\$entity)) {",
            "    throw new ClientException(ClientException::ERROR);",
            "}",
            "",
            "return ['status' => 204, 'message' => 'Deleted'];"
        ]);
    }
}