<?php
declare(strict_types=1);

namespace WCBulkPriceManager\Application;

use Closure;
use Exception;
use ReflectionClass;
use ReflectionNamedType;
use ReflectionParameter;;

class Container {                


	/** @var self|null Singleton instance of the container */
	private static ?self $instance = null;

	/** @var array<string, Closure> Registered factory closures */
	private array $bindings = [];

	/** @var array<string, mixed> Resolved singleton instances */
	private array $instances = [];

	/** Private constructor — use getInstance() */
	private function __construct() {}

	/**
	 * Returns the singleton container instance
	 */
	public static function getInstance(): self {
		if ( self::$instance === null ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Bind a factory closure. Creates a new instance on every call
	 */
	public function bind( string $abstract, Closure $factory ): void {
		$this->bindings[ $abstract ] = $factory;
	}

	/**
	 * Bind a singleton factory. The instance is created once and cached
     */
	public function singleton( string $abstract, Closure $factory ): void {
		$this->bindings[ $abstract ] = function ( self $container ) use ( $abstract, $factory ) {
			if ( ! array_key_exists( $abstract, $this->instances ) ) {
				$this->instances[ $abstract ] = $factory( $container );
			}
			return $this->instances[ $abstract ];
		};
	}

	/**
	 * Resolve an abstract identifier from the container
	 *
	 * Falls back to automatic constructor injection if no explicit binding exists
	 */
	public function get( string $abstract ): mixed {
		if ( isset( $this->bindings[ $abstract ] ) ) {
			return ( $this->bindings[ $abstract ] )( $this );
		}

		if ( class_exists( $abstract ) ) {
			return $this->resolve( $abstract );
		}

		throw new Exception(
			sprintf( 'WC BPM Container: [%s] could not be resolved. Register it or ensure the class exists.', $abstract )
		);
	}

    /**
	 * Check if an abstract is registered in the container
	 */
	public function has( string $abstract ): bool {
		return isset( $this->bindings[ $abstract ] ) || class_exists( $abstract );
	}

    // -------------------------------------------------------------------------
	// Private helpers
	// -------------------------------------------------------------------------

	/**
	 * Automatically resolve a class via Reflection, injecting dependencies
	 */
    private function resolve( string $class ): object {
		$reflector = new ReflectionClass( $class );

		if ( ! $reflector->isInstantiable() ) {
			throw new Exception( sprintf( 'WC BPM Container: [%s] is not instantiable.', $class ) );
		}

		$constructor = $reflector->getConstructor();

		if ( $constructor === null ) {
			return new $class();
		}

		$dependencies = array_map(
			fn( ReflectionParameter $param ) => $this->resolveParameter( $param, $class ),
			$constructor->getParameters()
		);

		return $reflector->newInstanceArgs( $dependencies );
	}

    /**
	 * Resolve a single constructor parameter
	 */
	private function resolveParameter( ReflectionParameter $param, string $class ): mixed {
		$type = $param->getType();

		// Class/interface type-hint → recurse into the container
		if ( $type instanceof ReflectionNamedType && ! $type->isBuiltin() ) {
			return $this->get( $type->getName() );
		}

		// Primitive with a default value → use it
		if ( $param->isDefaultValueAvailable() ) {
			return $param->getDefaultValue();
		}

		// Optional parameter → null
		if ( $param->isOptional() ) {
			return null;
		}

		throw new Exception(
			sprintf(
				'WC BPM Container: Cannot resolve primitive parameter $%s in [%s].',
				$param->getName(),
				$class
			)
		);
	}

}