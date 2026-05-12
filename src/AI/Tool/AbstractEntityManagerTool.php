<?php

declare(strict_types=1);

namespace App\AI\Tool;

use App\AI\Contract\ToolInterface;
use App\AI\Core\ChangesetManager;
use App\AI\Domain\ValueObject\ToolOutput;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Constraints\Choice;
use Symfony\Component\Validator\Constraints\LessThan;
use Symfony\Component\Validator\Constraints\Positive;
use Symfony\Component\Validator\Constraints\PositiveOrZero;
use Symfony\Component\Validator\ConstraintViolationListInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

abstract class AbstractEntityManagerTool implements ToolInterface
{
    public function __construct(
        protected readonly EntityManagerInterface $em,
        protected readonly SerializerInterface $serializer,
        protected readonly ValidatorInterface $validator,
        protected readonly ChangesetManager $changesetManager,
    ) {}

    abstract protected function getDtoClass(): string;

    abstract protected function getToolName(): string;

    abstract protected function getToolDescription(): string;

    abstract protected function handle(object $dto, object $user): ToolOutput;

    final public function getName(): string
    {
        return $this->getToolName();
    }

    /**
     * @return array<string, mixed>
     */
    final public function getDefinition(): array
    {
        return [
            'name' => $this->getName(),
            'description' => $this->getToolDescription(),
            'parameters' => $this->buildParameterSchema(),
        ];
    }

    /**
     * @param array<string, mixed> $args
     */
    final public function execute(array $args, object $user): ToolOutput
    {
        try {
            $json = json_encode($args);
            if ($json === false) {
                return $this->validationError('Impossible de traiter les arguments fournis.');
            }

            $dto = $this->serializer->deserialize($json, $this->getDtoClass(), 'json');
        } catch (\Exception $e) {
            return $this->validationError('Erreur de désérialisation: ' . $e->getMessage());
        }

        $errors = $this->validator->validate($dto);
        if ($errors->count() > 0) {
            return $this->validationError($this->formatValidationErrors($errors));
        }

        return $this->handle($dto, $user);
    }

    protected function createOutput(string $summary, array $uiPayload = [], bool $pendingChange = false, ?object $changeset = null): ToolOutput
    {
        return new ToolOutput(
            llmSummary: $summary,
            uiPayload: $uiPayload,
            hasPendingChange: $pendingChange,
            pendingChangeset: $changeset,
        );
    }

    protected function validationError(string $message): ToolOutput
    {
        return new ToolOutput(
            llmSummary: $message,
            uiPayload: ['type' => 'validation_error', 'error' => $message],
            hasPendingChange: false,
            pendingChangeset: null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function buildParameterSchema(): array
    {
        $dtoClass = $this->getDtoClass();
        $ref = new \ReflectionClass($dtoClass);
        $constructor = $ref->getConstructor();

        if ($constructor === null) {
            return ['type' => 'object', 'properties' => [], 'required' => []];
        }

        $properties = [];
        $required = [];

        foreach ($constructor->getParameters() as $param) {
            $name = $param->getName();
            $type = $param->getType();
            $isNullable = $type instanceof \ReflectionNamedType && $type->allowsNull();
            $typeName = $type instanceof \ReflectionNamedType ? $type->getName() : 'string';

            $schema = $this->typeToSchema($typeName);

            if ($ref->hasProperty($name)) {
                $prop = $ref->getProperty($name);
                foreach ($prop->getAttributes() as $attr) {
                    $instance = $attr->newInstance();
                    if ($instance instanceof Choice) {
                        $schema['enum'] = $instance->choices;
                    }
                    if ($instance instanceof Positive || $instance instanceof PositiveOrZero) {
                        $schema['type'] = 'integer';
                        $schema['minimum'] = $instance instanceof Positive ? 1 : 0;
                    }
                    if ($instance instanceof LessThan) {
                        $schema['maximum'] = $instance->value;
                    }
                }
            }

            $schema['description'] = $this->paramNameToDescription($name);

            $properties[$name] = $schema;

            if (!$isNullable && $name === 'action') {
                $required[] = $name;
            }
        }

        return [
            'type' => 'object',
            'properties' => $properties,
            'required' => $required,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function typeToSchema(string $typeName): array
    {
        return match ($typeName) {
            'int', 'integer' => ['type' => 'integer'],
            'float' => ['type' => 'number'],
            'bool', 'boolean' => ['type' => 'boolean'],
            'array' => ['type' => 'array'],
            default => ['type' => 'string'],
        };
    }

    private function paramNameToDescription(string $name): string
    {
        $labels = [
            'action' => 'Opération à effectuer',
            'id' => 'Identifiant unique',
            'status' => 'Statut à filtrer',
            'new_status' => 'Nouveau statut',
            'department' => 'Département',
            'search' => 'Terme de recherche',
            'limit' => 'Nombre maximum de résultats',
            'title' => 'Titre',
            'description' => 'Description',
            'location' => 'Lieu',
            'employment_type' => 'Type d\'emploi',
            'salary_min' => 'Salaire minimum',
            'salary_max' => 'Salaire maximum',
            'job_offer_id' => 'ID de l\'offre d\'emploi',
            'candidate_name' => 'Nom du candidat',
            'email' => 'Adresse email',
            'application_id' => 'ID de la candidature',
            'from_date' => 'Date de début (Y-m-d)',
            'to_date' => 'Date de fin (Y-m-d)',
            'type' => 'Type',
            'date' => 'Date et heure (Y-m-d H:i)',
            'duration' => 'Durée en minutes',
            'notes' => 'Notes',
            'meeting_link' => 'Lien de réunion',
            'result' => 'Résultat',
        ];

        return $labels[$name] ?? $name;
    }

    protected function getUserId(object $user): int
    {
        if (method_exists($user, 'getId')) {
            return $user->getId();
        }
        return 0;
    }

    private function formatValidationErrors(ConstraintViolationListInterface $errors): string
    {
        $lines = ['Erreur de validation:'];
        foreach ($errors as $error) {
            $invalidValue = $error->getInvalidValue();
            $invalidStr = is_scalar($invalidValue) ? (string) $invalidValue : 'valeur invalide';
            $lines[] = sprintf(
                "- %s: '%s' — %s",
                $error->getPropertyPath(),
                $invalidStr,
                $error->getMessage(),
            );
        }
        return implode("\n", $lines);
    }
}
