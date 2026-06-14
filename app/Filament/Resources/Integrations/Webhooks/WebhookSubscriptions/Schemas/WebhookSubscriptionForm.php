<?php

namespace App\Filament\Resources\Integrations\Webhooks\WebhookSubscriptions\Schemas;

use App\Domain\Webhooks\Enums\WebhookEvent;
use App\Security\SsrfValidator;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use InvalidArgumentException;

class WebhookSubscriptionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Configuración')
                    ->columns(1)
                    ->schema([
                        TextInput::make('url')
                            ->label('URL de destino')
                            ->url()
                            ->required()
                            ->maxLength(2048)
                            ->helperText('Solo HTTPS. Debe ser accesible públicamente (no IPs privadas ni internas).')
                            ->rules([
                                fn (): \Closure => function (string $attribute, mixed $value, \Closure $fail): void {
                                    if (! filled($value)) {
                                        return;
                                    }
                                    try {
                                        SsrfValidator::validate($value);
                                    } catch (InvalidArgumentException $e) {
                                        $fail($e->getMessage());
                                    }
                                },
                            ]),

                        CheckboxList::make('events')
                            ->label('Eventos a enviar')
                            ->options(
                                collect(WebhookEvent::cases())
                                    ->mapWithKeys(fn (WebhookEvent $e) => [$e->value => $e->label()])
                                    ->toArray()
                            )
                            ->columns(2)
                            ->required(),

                        Toggle::make('is_active')
                            ->label('Activo')
                            ->default(true),
                    ]),

                Section::make('Seguridad')
                    ->schema([
                        TextInput::make('secret')
                            ->label('Secret (HMAC-SHA256)')
                            ->default(fn (): string => bin2hex(random_bytes(32)))
                            ->readOnly()
                            ->helperText('Se usa para firmar cada delivery. Cópialo antes de guardar — no podrás verlo nuevamente.')
                            ->dehydrated(fn (?string $state): bool => filled($state)),
                    ])
                    ->visibleOn('create'),
            ]);
    }
}
