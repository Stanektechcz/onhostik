<?php($breadcrumbTitle = $service->label)
@php($breadcrumbItems = [__('panel.nav.services') => route('panel.services.index'), $service->label => ''])

@section('title', $service->label)

@section('content')
    <div class="container-fluid">
        <x-panel.flash />

        {{-- KPI strip --}}
        <div class="row mb-1">
            <div class="col-sm-6 col-xl-3">
                <div class="small-widget">
                    <div class="card card-no-border">
                        <div class="card-body">
                            <div class="d-flex align-items-center gap-3">
                                <div class="bg-light-{{ match($service->status) { \App\Domains\Provisioning\Enums\ServiceStatus::Active => 'success', \App\Domains\Provisioning\Enums\ServiceStatus::Suspended => 'warning', default => 'secondary' } }} rounded p-2">
                                    <i data-feather="server" class="font-{{ match($service->status) { \App\Domains\Provisioning\Enums\ServiceStatus::Active => 'success', \App\Domains\Provisioning\Enums\ServiceStatus::Suspended => 'warning', default => 'secondary' } }}"></i>
                                </div>
                                <div>
                                    <x-panel.status-badge :status="$service->status" />
                                    <span class="f-light f-12 d-block">{{ __('panel.common.status') }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-xl-3">
                <div class="small-widget">
                    <div class="card card-no-border">
                        <div class="card-body">
                            <div class="d-flex align-items-center gap-3">
                                <div class="bg-light-primary rounded p-2"><i data-feather="calendar" class="font-primary"></i></div>
                                <div>
                                    <h5 class="mb-0 f-w-600 {{ $service->next_due_date?->isPast() ? 'font-danger' : '' }}">
                                        {{ $service->next_due_date?->format('d.m.Y') ?? '—' }}
                                    </h5>
                                    <span class="f-light f-12">{{ __('panel.services.next_due') }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-xl-3">
                <div class="small-widget">
                    <div class="card card-no-border">
                        <div class="card-body">
                            <div class="d-flex align-items-center gap-3">
                                <div class="bg-light-{{ $monitor?->uptime_percent >= 99 ? 'success' : ($monitor?->uptime_percent >= 95 ? 'warning' : 'danger') }} rounded p-2">
                                    <i data-feather="activity" class="font-{{ $monitor?->uptime_percent >= 99 ? 'success' : ($monitor?->uptime_percent >= 95 ? 'warning' : 'danger') }}"></i>
                                </div>
                                <div>
                                    <h5 class="mb-0 f-w-600">{{ $monitor ? $monitor->uptime_percent . ' %' : '—' }}</h5>
                                    <span class="f-light f-12">Uptime</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-xl-3">
                <div class="small-widget">
                    <div class="card card-no-border">
                        <div class="card-body">
                            <div class="d-flex align-items-center gap-3">
                                @php($sslDays = $monitor?->ssl_expires_at ? now()->diffInDays($monitor->ssl_expires_at, false) : null)
                                <div class="bg-light-{{ $sslDays === null ? 'secondary' : ($sslDays < 14 ? 'danger' : ($sslDays < 30 ? 'warning' : 'success')) }} rounded p-2">
                                    <i data-feather="lock" class="font-{{ $sslDays === null ? 'secondary' : ($sslDays < 14 ? 'danger' : ($sslDays < 30 ? 'warning' : 'success')) }}"></i>
                                </div>
                                <div>
                                    <h5 class="mb-0 f-w-600">{{ $sslDays !== null ? $sslDays . 'd' : '—' }}</h5>
                                    <span class="f-light f-12">SSL platnost</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-xl-4">
                {{-- Service info --}}
                <x-panel.card :title="$service->label" :subtitle="$service->product?->name">
                    <table class="table table-borderless mb-0">
                        @if($service->external_id)
                            <tr>
                                <td class="f-light ps-0 f-12">{{ __('panel.services.external_id') }}</td>
                                <td><code>{{ $service->external_id }}</code></td>
                            </tr>
                        @endif
                        @if($service->domainRegistration)
                            <tr>
                                <td class="f-light ps-0 f-12">{{ __('panel.services.domain') }}</td>
                                <td>
                                    <a href="{{ route('panel.domains.show', $service->domainRegistration) }}">
                                        {{ $service->domainRegistration->fqdn() }}
                                    </a>
                                </td>
                            </tr>
                        @endif
                        <tr>
                            <td class="f-light ps-0 f-12">Produkt</td>
                            <td>{{ $service->product?->name ?? '—' }}</td>
                        </tr>
                        <tr>
                            <td class="f-light ps-0 f-12">Aktivní od</td>
                            <td>{{ $service->created_at?->format('d.m.Y') }}</td>
                        </tr>
                    </table>
                </x-panel.card>

                {{-- Resources --}}
                @if(!empty($service->resources))
                    <x-panel.card :title="__('panel.services.resources')">
                        @php($res = $service->resources)
                        <div class="row g-2">
                            @if(isset($res['cpu']))
                                <div class="col-6">
                                    <div class="border rounded p-2 text-center">
                                        <i data-feather="cpu" class="font-primary mb-1" style="width:18px;height:18px"></i>
                                        <div class="f-w-600">{{ $res['cpu'] }} vCPU</div>
                                        <div class="f-light f-12">Procesor</div>
                                    </div>
                                </div>
                            @endif
                            @if(isset($res['ram_mb']))
                                <div class="col-6">
                                    <div class="border rounded p-2 text-center">
                                        <i data-feather="database" class="font-success mb-1" style="width:18px;height:18px"></i>
                                        <div class="f-w-600">{{ round($res['ram_mb'] / 1024, 1) }} GB</div>
                                        <div class="f-light f-12">RAM</div>
                                    </div>
                                </div>
                            @endif
                            @if(isset($res['disk_mb']))
                                <div class="col-6">
                                    <div class="border rounded p-2 text-center">
                                        <i data-feather="hard-drive" class="font-warning mb-1" style="width:18px;height:18px"></i>
                                        <div class="f-w-600">{{ round($res['disk_mb'] / 1024, 1) }} GB</div>
                                        <div class="f-light f-12">Disk</div>
                                    </div>
                                </div>
                            @endif
                            @if(isset($res['bandwidth_gb']))
                                <div class="col-6">
                                    <div class="border rounded p-2 text-center">
                                        <i data-feather="wifi" class="font-info mb-1" style="width:18px;height:18px"></i>
                                        <div class="f-w-600">{{ $res['bandwidth_gb'] }} GB</div>
                                        <div class="f-light f-12">Přenos / měs.</div>
                                    </div>
                                </div>
                            @endif
                        </div>
                        @foreach(array_diff_key($res, array_flip(['cpu','ram_mb','disk_mb','bandwidth_gb','ipconfig'])) as $key => $val)
                            <div class="d-flex justify-content-between f-12 mt-2">
                                <span class="f-light">{{ $key }}</span>
                                <span>{{ is_array($val) ? json_encode($val) : $val }}</span>
                            </div>
                        @endforeach
                    </x-panel.card>
                @endif

                {{-- Actions --}}
                <x-panel.card title="Akce">
                    <div class="d-flex flex-wrap gap-2">
                        @if($mockMode && $service->status === \App\Domains\Provisioning\Enums\ServiceStatus::Active)
                            <form method="POST" action="{{ route('panel.services.wordpress', $service) }}">
                                @csrf
                                <button type="submit" class="btn btn-primary btn-sm">
                                    <i data-feather="code" style="width:13px;height:13px"></i>
                                    {{ __('panel.services.wp_install') }}
                                    <span class="badge badge-light-warning ms-1">mock</span>
                                </button>
                            </form>
                        @endif
                        <button type="button" class="btn btn-outline-secondary btn-sm" disabled>{{ __('panel.services.renew_placeholder') }}</button>
                        <button type="button" class="btn btn-outline-secondary btn-sm" disabled>{{ __('panel.services.upgrade_placeholder') }}</button>
                        <button type="button" class="btn btn-outline-secondary btn-sm" disabled>{{ __('panel.services.cancel_placeholder') }}</button>
                    </div>
                    <p class="f-light f-12 mb-0 mt-2">{{ __('panel.services.credentials') }}: {{ __('panel.services.credentials_note') }}</p>
                </x-panel.card>
            </div>

            <div class="col-xl-8">
                {{-- Monitoring --}}
                <x-panel.card :title="__('panel.services.monitoring')">
                    @if($monitor === null)
                        <p class="f-light mb-0">{{ __('panel.common.empty') }}</p>
                    @else
                        @php
                            $uptime = $monitor->uptime_percent ?? 0;
                            $uptimeColor = $uptime >= 99 ? 'success' : ($uptime >= 95 ? 'warning' : 'danger');
                        ?>
                        <div class="row align-items-center mb-3">
                            <div class="col-md-6">
                                <div class="d-flex justify-content-between mb-1">
                                    <span class="f-light f-12">Uptime (30 dní)</span>
                                    <span class="f-w-600 f-12"><?php echo e($uptime); ?> %</span>
                                </div>
                                <div class="progress" style="height: 8px;">
                                    <div class="progress-bar bg-<?php echo e($uptimeColor); ?>"
                                         role="progressbar"
                                         style="width: <?php echo e($uptime); ?>%"
                                         aria-valuenow="<?php echo e($uptime); ?>"
                                         aria-valuemin="0"
                                         aria-valuemax="100">
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <p class="f-light f-12 mb-1"><?php echo e(__('panel.services.last_check')); ?></p>
                                <p class="mb-0 f-12"><?php echo e($monitor->last_check_at?->diffForHumans() ?? '—'); ?></p>
                            </div>
                            <div class="col-md-3">
                                <p class="f-light f-12 mb-1">SSL platnost</p>
                                <p class="mb-0 f-12 <?php echo e($sslDays !== null && $sslDays < 30 ? 'text-danger' : ''); ?>">
                                    <?php echo e($monitor->ssl_expires_at?->format('d.m.Y') ?? '—'); ?>

                                </p>
                            </div>
                        </div>
                        <div class="d-flex gap-2 align-items-center">
                            <?php if (isset($component)) { $__componentOriginal4ace6bef108acc6b68451beacef627ae = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal4ace6bef108acc6b68451beacef627ae = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.panel.status-badge','data' => ['status' => $monitor->status]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('panel.status-badge'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['status' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($monitor->status)]); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal4ace6bef108acc6b68451beacef627ae)): ?>
<?php $attributes = $__attributesOriginal4ace6bef108acc6b68451beacef627ae; ?>
<?php unset($__attributesOriginal4ace6bef108acc6b68451beacef627ae); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal4ace6bef108acc6b68451beacef627ae)): ?>
<?php $component = $__componentOriginal4ace6bef108acc6b68451beacef627ae; ?>
<?php unset($__componentOriginal4ace6bef108acc6b68451beacef627ae); ?>
<?php endif; ?>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($monitor->status->value === 'down'): ?>
                                <span class="f-12 text-danger">
                                    <i data-feather="alert-triangle" style="width:12px;height:12px"></i>
                                    Služba není dostupná
                                </span>
                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        </div>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                 <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal)): ?>
<?php $attributes = $__attributesOriginal; ?>
<?php unset($__attributesOriginal); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal)): ?>
<?php $component = $__componentOriginal; ?>
<?php unset($__componentOriginal); ?>
<?php endif; ?>

                
                <?php if (isset($component)) { $__componentOriginal45416b6f4957cdf01291e6fea7f206e5 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal45416b6f4957cdf01291e6fea7f206e5 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.panel.card','data' => ['title' => __('panel.services.backups')]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('panel.card'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['title' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(__('panel.services.backups'))]); ?>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($mockMode && $service->status === \App\Domains\Provisioning\Enums\ServiceStatus::Active): ?>
                        <form method="POST" action="<?php echo e(route('panel.services.backup', $service)); ?>" class="mb-3">
                            <?php echo csrf_field(); ?>
                            <button type="submit" class="btn btn-outline-primary btn-sm">
                                <i data-feather="archive" style="width:13px;height:13px"></i>
                                <?php echo e(__('panel.services.request_backup')); ?>

                                <span class="badge badge-light-warning ms-1">mock</span>
                            </button>
                        </form>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['backup'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><div class="text-danger f-12 mb-2"><?php echo e($message); ?></div><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['wordpress'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><div class="text-danger f-12 mb-2"><?php echo e($message); ?></div><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($backupJobs->isEmpty()): ?>
                        <p class="f-light mb-0"><?php echo e(__('panel.common.empty')); ?></p>
                    <?php else: ?>
                        <?php if (isset($component)) { $__componentOriginal8d4c227dc8ae599eadc8d2e562ce6cb2 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal8d4c227dc8ae599eadc8d2e562ce6cb2 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.panel.data-table','data' => ['headers' => [__('panel.common.date'), __('panel.common.status'), 'Velikost']]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('panel.data-table'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['headers' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute([__('panel.common.date'), __('panel.common.status'), 'Velikost'])]); ?>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $backupJobs; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $backupJob): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                <tr>
                                    <td><?php echo e($backupJob->created_at?->format('d.m.Y H:i')); ?></td>
                                    <td><?php if (isset($component)) { $__componentOriginal4ace6bef108acc6b68451beacef627ae = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal4ace6bef108acc6b68451beacef627ae = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.panel.status-badge','data' => ['status' => $backupJob->status]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('panel.status-badge'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['status' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($backupJob->status)]); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal4ace6bef108acc6b68451beacef627ae)): ?>
<?php $attributes = $__attributesOriginal4ace6bef108acc6b68451beacef627ae; ?>
<?php unset($__attributesOriginal4ace6bef108acc6b68451beacef627ae); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal4ace6bef108acc6b68451beacef627ae)): ?>
<?php $component = $__componentOriginal4ace6bef108acc6b68451beacef627ae; ?>
<?php unset($__componentOriginal4ace6bef108acc6b68451beacef627ae); ?>
<?php endif; ?></td>
                                    <td><?php echo e($backupJob->size_mb ? $backupJob->size_mb . ' MB' : '—'); ?></td>
                                </tr>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                         <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal8d4c227dc8ae599eadc8d2e562ce6cb2)): ?>
<?php $attributes = $__attributesOriginal8d4c227dc8ae599eadc8d2e562ce6cb2; ?>
<?php unset($__attributesOriginal8d4c227dc8ae599eadc8d2e562ce6cb2); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal8d4c227dc8ae599eadc8d2e562ce6cb2)): ?>
<?php $component = $__componentOriginal8d4c227dc8ae599eadc8d2e562ce6cb2; ?>
<?php unset($__componentOriginal8d4c227dc8ae599eadc8d2e562ce6cb2); ?>
<?php endif; ?>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                 <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal45416b6f4957cdf01291e6fea7f206e5)): ?>
<?php $attributes = $__attributesOriginal45416b6f4957cdf01291e6fea7f206e5; ?>
<?php unset($__attributesOriginal45416b6f4957cdf01291e6fea7f206e5); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal45416b6f4957cdf01291e6fea7f206e5)): ?>
<?php $component = $__componentOriginal45416b6f4957cdf01291e6fea7f206e5; ?>
<?php unset($__componentOriginal45416b6f4957cdf01291e6fea7f206e5); ?>
<?php endif; ?>

                
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($service->provisioningTasks->isNotEmpty()): ?>
                    <?php if (isset($component)) { $__componentOriginal45416b6f4957cdf01291e6fea7f206e5 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal45416b6f4957cdf01291e6fea7f206e5 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.panel.card','data' => ['title' => __('panel.services.tasks')]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('panel.card'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['title' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(__('panel.services.tasks'))]); ?>
                        <div class="activity-log">
                            <div class="basic-timeline">
                                <ul>
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $service->provisioningTasks; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $task): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                        <?php
                                            $dotColor = match($task->status) {
                                                \App\Domains\Provisioning\Enums\TaskStatus::Success => 'success',
                                                \App\Domains\Provisioning\Enums\TaskStatus::Failed,
                                                \App\Domains\Provisioning\Enums\TaskStatus::ManualReview => 'danger',
                                                \App\Domains\Provisioning\Enums\TaskStatus::Running,
                                                \App\Domains\Provisioning\Enums\TaskStatus::Retrying => 'warning',
                                                default => 'primary',
                                            };
                                        ?>
                                        <li>
                                            <div class="timeline-dot-<?php echo e($dotColor); ?>"></div>
                                            <div class="ms-4 pb-1">
                                                <div class="d-flex justify-content-between">
                                                    <div>
                                                        <span class="f-w-500"><?php echo e($task->operation); ?></span>
                                                        <?php if (isset($component)) { $__componentOriginal4ace6bef108acc6b68451beacef627ae = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal4ace6bef108acc6b68451beacef627ae = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.panel.status-badge','data' => ['status' => $task->status]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('panel.status-badge'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['status' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($task->status)]); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal4ace6bef108acc6b68451beacef627ae)): ?>
<?php $attributes = $__attributesOriginal4ace6bef108acc6b68451beacef627ae; ?>
<?php unset($__attributesOriginal4ace6bef108acc6b68451beacef627ae); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal4ace6bef108acc6b68451beacef627ae)): ?>
<?php $component = $__componentOriginal4ace6bef108acc6b68451beacef627ae; ?>
<?php unset($__componentOriginal4ace6bef108acc6b68451beacef627ae); ?>
<?php endif; ?>
                                                    </div>
                                                    <span class="f-light f-12 text-nowrap">
                                                        <?php echo e($task->finished_at?->format('d.m. H:i') ?? $task->created_at?->format('d.m. H:i')); ?>

                                                    </span>
                                                </div>
                                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($task->error_message): ?>
                                                    <p class="f-12 text-danger mb-0 mt-1"><?php echo e($task->error_message); ?></p>
                                                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                            </div>
                                        </li>
                                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                </ul>
                            </div>
                        </div>
                     <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal45416b6f4957cdf01291e6fea7f206e5)): ?>
<?php $attributes = $__attributesOriginal45416b6f4957cdf01291e6fea7f206e5; ?>
<?php unset($__attributesOriginal45416b6f4957cdf01291e6fea7f206e5); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal45416b6f4957cdf01291e6fea7f206e5)): ?>
<?php $component = $__componentOriginal45416b6f4957cdf01291e6fea7f206e5; ?>
<?php unset($__componentOriginal45416b6f4957cdf01291e6fea7f206e5); ?>
<?php endif; ?>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>
        </div>
    </div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.panel', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>