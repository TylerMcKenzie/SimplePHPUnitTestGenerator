<?php
/**
 * Created by Tyler Mckenzie
 * Date: 4/10/2019
 *
 * The purpose of this app is to provide a way to quickly generate tests for our codebase
 */

namespace Phptestgen;

use Go\ParserReflection\ReflectionFile;
use Tmckenzie\PHPSimpleCodeGenerator\ClassGenerator;
use Tmckenzie\PHPSimpleCodeGenerator\ClassMethod;
use Tmckenzie\PHPSimpleCodeGenerator\ClassProperty;

class PHPUnitGenerator
{
	protected $class_generator;

	protected $file;

	public function __construct() {
		$this->class_generator = new ClassGenerator();
	}

	public function setFile($file)
	{
		$this->file = $file;
	}

	public function getFile()
	{
		return $this->file;
	}

	public function run()
	{
		$reflection_file = new ReflectionFile($this->getFile());
		$namespaces = $reflection_file->getFileNamespaces();
		$classes = (array_pop($namespaces))->getClasses();
		$reflection_class = array_pop($classes);

		// The class we want will always be the last one we loaded
		$declared_classes = get_declared_classes();
		$classname = $declared_classes[count($declared_classes)-1];

		$this->class_generator->addUses($classname);

		$reflection_methods = $reflection_class->getMethods();

		$test_class_name = $this->generateTestClassname($reflection_class);
		$this->class_generator->setClassname($test_class_name);
		$this->class_generator->setExtends("PHPUnit_Framework_TestCase");

		foreach($reflection_methods as $method) {
			$test_method = $this->generateTestClassMethodForReflectionMethod($method);

			if (!empty($test_method)) {
				$this->class_generator->addMethod($test_method);
			}
		}

		echo $this->class_generator::format($this->class_generator->generateClass());
	}

	private function generateTestClassname(\ReflectionClass $reflection_class)
	{
		$namespace_arr = explode("\\", $reflection_class->getName());
		$class_name = array_pop($namespace_arr);
		$test_name = "{$class_name}Test";
		return $test_name;
	}

	private function generateTestClassMethodForReflectionMethod(\ReflectionMethod $method)
	{
		$method_name = $method->getName();

		if ($method->isPrivate() || $method->isProtected()) {
			// echo "Skipping method: '{$method_name}' due to it not being public.\n";
			return;
		}

		$test_method = new ClassMethod();
		$test_method->setVisibility("public");

		$parameters = $method->getParameters();

		// Constructor test
		if ($method->isConstructor()) {
			if (!empty($parameters)) {
				foreach ($parameters as $parameter) {
					$name = $parameter->getName();
					$type = "{$parameter->getType()}";

					$doc = "/**\n * @var {$type} \\\\TODO: ADD DESCRIPTION \n */";

					$class_property = new ClassProperty();
					$class_property->setName($name);
					$class_property->setDoc($doc);
					$class_property->setType($type);
					$class_property->setVisibility("private");

					$this->class_generator->addProperty($class_property);
				}
			}

			// Set Up method
			$setUp_method = new ClassMethod();
			$setUp_method->setName("setUp");
			$setUp_method->setVisibility("public");
			$setUp_method->setDoc("/**\n * Initializes each property with a valid value.\n */");

			$setUp_function_contents = "";
			foreach ($this->class_generator->getProperties() as $property) {
				if (!empty($property->getDefaultValue())) {
					$default = $property->getDefaultValue();
				} else {
					$default = null;
				}

				switch($property->getType()) {
					case "bool":
						if (!empty($default)) {
							$value = $default ? "true;" : "false;";
						} else {
							$value = "true;";
						}

						break;
					case "array":
						$value = "[]; \\\\TODO: UPDATE THIS ARRAY STRUCTURE IF THERE IS ONE";
						break;
					case "string":
						if (!empty($default)) {
							$value = "\"{$default}\";";
						} else {
							$value = "\"string\";";
						}
						break;
					case "int":
						if (!empty($default)) {
							$value = "{$default};";
						} else {
							$value = "0;";
						}

						break;
					default:
						$value = "\$this->getMockBuilder(\\" .$property->getType() . "::class)->disableOriginalConstructor()->getMock();";
						break;
				}

				$setUp_function_contents .= "\$this->{$property->getName()} = {$value}\n";
			}

			$setUp_function_contents = rtrim($setUp_function_contents, "\n");

			$setUp_method->setFunctionContents($setUp_function_contents);

			$this->class_generator->addMethod($setUp_method);

			// Initialize function
			$namespace_arr = explode("\\", $method->getDeclaringClass()->getName());
			$class_name = array_pop($namespace_arr);

			$initialize_method = new ClassMethod();
			$initialize_method->setName("initialize");
			$initialize_method->setVisibility("private");
			$initialize_method->setDoc("/**\n * Helper method to initialize the class {$class_name}\n *\n * @return {$class_name} class object to test with\n */");

			$initialize_method_function_class_var_name = preg_replace("/([A-Z])/", "_$0", $class_name);
			$initialize_method_function_class_var_name = strtolower(ltrim($initialize_method_function_class_var_name, "_"));

			$initialize_method_class_props = "";
			foreach ($this->class_generator->getProperties() as $property) {
				$initialize_method_class_props .= "\$this->{$property->getName()},\n";
			}

			$initialize_method_class_props = rtrim($initialize_method_class_props, ",\n");

			$initialize_method_function_contents = "\$" . $initialize_method_function_class_var_name . " = new {$class_name}(\n{$initialize_method_class_props}\n);\n\nreturn \${$initialize_method_function_class_var_name};";

			$initialize_method->setFunctionContents($initialize_method_function_contents);

			$this->class_generator->addMethod($initialize_method);

			// Test Constructor
			$test_method->setName("testConstructor");
			$test_method->setDoc("/**\n * Testing valid instantiation of {$class_name}\n */");
			$test_method->setFunctionContents("\$this->assertInstanceOf(\n\"{$method->getDeclaringClass()->getName()}\",\n\$this->initialize()\n);");

			return $test_method;
		} else {
			// Regular test
			$valid_test_name = "testValid" . ucfirst($method_name);
			$invalid_test_name = "testInvalid" . ucfirst($method_name);


			// Valid test
			$valid_test_method = new ClassMethod();

			$valid_test_method->setName($valid_test_name);
			$valid_test_method->setDoc("/**\n * TODO: ADD TEST DESCRIPTION\n */");
			$valid_test_method->setVisibility("public");

			$this->class_generator->addMethod($valid_test_method);

			// Invalid test
			if (!empty($parameters)) {
				// Todo create type/argument tests
				$invalid_test_method = new ClassMethod();

				$invalid_test_method->setName($invalid_test_name);
				$invalid_test_method->setDoc("/**\n * TODO: ADD TEST DESCRIPTION\n */");
				$invalid_test_method->setVisibility("public");

				$this->class_generator->addMethod($invalid_test_method);
			} else {
				$invalid_test_method = new ClassMethod();

				$invalid_test_method->setName($invalid_test_name);
				$invalid_test_method->setDoc("/**\n * TODO: ADD TEST DESCRIPTION\n */");
				$invalid_test_method->setVisibility("public");

				$this->class_generator->addMethod($invalid_test_method);
			}

			return null;
		}
	}
}
