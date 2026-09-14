<?php declare(strict_types = 1);

// odsl-C:\Users\medion\Desktop\ONHOST-NEW\onhost-platform\domains\Tax\TaxEngine.php-PHPStan\BetterReflection\Reflection\ReflectionClass-Onhost\Domain\Tax\TaxEngine
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-6.70.0.6-8.3.33-b18ab650d4af6f8899b07278080da6d6a4c4fe7370b1321b899fcf5daae03717',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'Onhost\\Domain\\Tax\\TaxEngine',
        'filename' => 'C:/Users/medion/Desktop/ONHOST-NEW/onhost-platform/domains/Tax/TaxEngine.php',
      ),
    ),
    'namespace' => 'Onhost\\Domain\\Tax',
    'name' => 'Onhost\\Domain\\Tax\\TaxEngine',
    'shortName' => 'TaxEngine',
    'isInterface' => false,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 32,
    'docComment' => '/**
 * Versioned VAT engine (blueprint §64.4, §23.8). Inputs: supplier legal entity,
 * customer B2B/B2C, country evidence, VAT ID validation, product classification,
 * supply date. Output per line: rate, UNCL5305 category, legal note. Nothing is
 * `if country == CZ then 21` — every decision references the rule version.
 *
 * Rule set shape (see database/seeders/TaxRuleSeeder):
 *   supplier: {country: CZ, vat_payer: true}
 *   standard_rates: {CZ: 21, SK: 23, …}
 *   eu_members: [...]
 *   oss: {registered: true, from: "2026-01-01"}   # B2C cross-border ESD taxed at destination
 *   product_classes: {esd: {}, domain: {}, hardware: {}}
 *   evidence: {require_two_pieces: true, strict: false}
 */',
    'attributes' => 
    array (
    ),
    'startLine' => 27,
    'endLine' => 135,
    'startColumn' => 1,
    'endColumn' => 1,
    'parentClassName' => NULL,
    'implementsClassNames' => 
    array (
    ),
    'traitClassNames' => 
    array (
    ),
    'immediateConstants' => 
    array (
      'CAT_STANDARD' => 
      array (
        'declaringClassName' => 'Onhost\\Domain\\Tax\\TaxEngine',
        'implementingClassName' => 'Onhost\\Domain\\Tax\\TaxEngine',
        'name' => 'CAT_STANDARD',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '\'S\'',
          'attributes' => 
          array (
            'startLine' => 29,
            'endLine' => 29,
            'startTokenPos' => 58,
            'startFilePos' => 1032,
            'endTokenPos' => 58,
            'endFilePos' => 1034,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 29,
        'endLine' => 29,
        'startColumn' => 5,
        'endColumn' => 36,
      ),
      'CAT_ZERO' => 
      array (
        'declaringClassName' => 'Onhost\\Domain\\Tax\\TaxEngine',
        'implementingClassName' => 'Onhost\\Domain\\Tax\\TaxEngine',
        'name' => 'CAT_ZERO',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '\'Z\'',
          'attributes' => 
          array (
            'startLine' => 31,
            'endLine' => 31,
            'startTokenPos' => 69,
            'startFilePos' => 1066,
            'endTokenPos' => 69,
            'endFilePos' => 1068,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 31,
        'endLine' => 31,
        'startColumn' => 5,
        'endColumn' => 32,
      ),
      'CAT_EXEMPT' => 
      array (
        'declaringClassName' => 'Onhost\\Domain\\Tax\\TaxEngine',
        'implementingClassName' => 'Onhost\\Domain\\Tax\\TaxEngine',
        'name' => 'CAT_EXEMPT',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '\'E\'',
          'attributes' => 
          array (
            'startLine' => 33,
            'endLine' => 33,
            'startTokenPos' => 80,
            'startFilePos' => 1102,
            'endTokenPos' => 80,
            'endFilePos' => 1104,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 33,
        'endLine' => 33,
        'startColumn' => 5,
        'endColumn' => 34,
      ),
      'CAT_REVERSE_CHARGE' => 
      array (
        'declaringClassName' => 'Onhost\\Domain\\Tax\\TaxEngine',
        'implementingClassName' => 'Onhost\\Domain\\Tax\\TaxEngine',
        'name' => 'CAT_REVERSE_CHARGE',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '\'AE\'',
          'attributes' => 
          array (
            'startLine' => 35,
            'endLine' => 35,
            'startTokenPos' => 91,
            'startFilePos' => 1146,
            'endTokenPos' => 91,
            'endFilePos' => 1149,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 35,
        'endLine' => 35,
        'startColumn' => 5,
        'endColumn' => 43,
      ),
      'CAT_OUT_OF_SCOPE' => 
      array (
        'declaringClassName' => 'Onhost\\Domain\\Tax\\TaxEngine',
        'implementingClassName' => 'Onhost\\Domain\\Tax\\TaxEngine',
        'name' => 'CAT_OUT_OF_SCOPE',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '\'O\'',
          'attributes' => 
          array (
            'startLine' => 37,
            'endLine' => 37,
            'startTokenPos' => 102,
            'startFilePos' => 1189,
            'endTokenPos' => 102,
            'endFilePos' => 1191,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 37,
        'endLine' => 37,
        'startColumn' => 5,
        'endColumn' => 40,
      ),
    ),
    'immediateProperties' => 
    array (
    ),
    'immediateMethods' => 
    array (
      'currentRules' => 
      array (
        'name' => 'currentRules',
        'parameters' => 
        array (
          'at' => 
          array (
            'name' => 'at',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 39,
                'endLine' => 39,
                'startTokenPos' => 118,
                'startFilePos' => 1254,
                'endTokenPos' => 118,
                'endFilePos' => 1257,
              ),
            ),
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionUnionType',
              'data' => 
              array (
                'types' => 
                array (
                  0 => 
                  array (
                    'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
                    'data' => 
                    array (
                      'name' => 'DateTimeInterface',
                      'isIdentifier' => false,
                    ),
                  ),
                  1 => 
                  array (
                    'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
                    'data' => 
                    array (
                      'name' => 'null',
                      'isIdentifier' => true,
                    ),
                  ),
                ),
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 39,
            'endLine' => 39,
            'startColumn' => 34,
            'endColumn' => 63,
            'parameterIndex' => 0,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'Onhost\\Domain\\Tax\\Models\\TaxRuleVersion',
            'isIdentifier' => false,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => NULL,
        'startLine' => 39,
        'endLine' => 53,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => true,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Onhost\\Domain\\Tax',
        'declaringClassName' => 'Onhost\\Domain\\Tax\\TaxEngine',
        'implementingClassName' => 'Onhost\\Domain\\Tax\\TaxEngine',
        'currentClassName' => 'Onhost\\Domain\\Tax\\TaxEngine',
        'aliasName' => NULL,
      ),
      'calculate' => 
      array (
        'name' => 'calculate',
        'parameters' => 
        array (
          'customer' => 
          array (
            'name' => 'customer',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'array',
                'isIdentifier' => true,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 60,
            'endLine' => 60,
            'startColumn' => 31,
            'endColumn' => 45,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'lines' => 
          array (
            'name' => 'lines',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'array',
                'isIdentifier' => true,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 60,
            'endLine' => 60,
            'startColumn' => 48,
            'endColumn' => 59,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
          'currency' => 
          array (
            'name' => 'currency',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionUnionType',
              'data' => 
              array (
                'types' => 
                array (
                  0 => 
                  array (
                    'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
                    'data' => 
                    array (
                      'name' => 'Onhost\\Platform\\Money\\Currency',
                      'isIdentifier' => false,
                    ),
                  ),
                  1 => 
                  array (
                    'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
                    'data' => 
                    array (
                      'name' => 'string',
                      'isIdentifier' => true,
                    ),
                  ),
                ),
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 60,
            'endLine' => 60,
            'startColumn' => 62,
            'endColumn' => 86,
            'parameterIndex' => 2,
            'isOptional' => false,
          ),
          'organizationId' => 
          array (
            'name' => 'organizationId',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 60,
                'endLine' => 60,
                'startTokenPos' => 277,
                'startFilePos' => 2386,
                'endTokenPos' => 277,
                'endFilePos' => 2389,
              ),
            ),
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionUnionType',
              'data' => 
              array (
                'types' => 
                array (
                  0 => 
                  array (
                    'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
                    'data' => 
                    array (
                      'name' => 'string',
                      'isIdentifier' => true,
                    ),
                  ),
                  1 => 
                  array (
                    'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
                    'data' => 
                    array (
                      'name' => 'null',
                      'isIdentifier' => true,
                    ),
                  ),
                ),
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 60,
            'endLine' => 60,
            'startColumn' => 89,
            'endColumn' => 118,
            'parameterIndex' => 3,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'array',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * @param  array{country:string, customer_class:string, vat_id?:?string, vat_status?:string, ip_country?:?string, product_class?:string, supply_date?:?string}  $customer
 * @param  list<array{key:string, net:Money, product_class?:string}>  $lines
 * @return array{calculation:TaxCalculation, lines:list<array{key:string, net:Money, rate:string, category:string, tax:Money, total:Money, note:?string}>, tax_total:Money, review_required:bool, reasons:list<string>}
 */',
        'startLine' => 60,
        'endLine' => 134,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => true,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Onhost\\Domain\\Tax',
        'declaringClassName' => 'Onhost\\Domain\\Tax\\TaxEngine',
        'implementingClassName' => 'Onhost\\Domain\\Tax\\TaxEngine',
        'currentClassName' => 'Onhost\\Domain\\Tax\\TaxEngine',
        'aliasName' => NULL,
      ),
    ),
    'traitsData' => 
    array (
      'aliases' => 
      array (
      ),
      'modifiers' => 
      array (
      ),
      'precedences' => 
      array (
      ),
      'hashes' => 
      array (
      ),
    ),
  ),
));