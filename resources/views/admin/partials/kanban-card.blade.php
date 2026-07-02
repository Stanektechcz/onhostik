<div class="kanban-card">
    <div class="d-flex justify-content-between align-items-start mb-1">
        <p class="card-title mb-0">{{ $task->title }}</p>
        <div class="dropdown ms-2">
            <button class="btn btn-link p-0 text-muted" data-bs-toggle="dropdown">
                <i data-feather="more-horizontal" style="width:14px;height:14px;"></i>
            </button>
            <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                <li>
                    <form method="POST" action="{{ route('admin.tasks.toggle', $task) }}">
                        @csrf
                        <button type="submit" class="dropdown-item f-12">
                            <i data-feather="arrow-right" style="width:12px;height:12px;"></i>
                            {{ $nextLabel }}
                        </button>
                    </form>
                </li>
                <li><a class="dropdown-item f-12" href="{{ route('admin.tasks') }}">
                    <i data-feather="edit-2" style="width:12px;height:12px;"></i> Upravit
                </a></li>
                <li><hr class="dropdown-divider"></li>
                <li>
                    <form method="POST" action="{{ route('admin.tasks.destroy', $task) }}"
                          onsubmit="return confirm('Smazat úkol?')">
                        @csrf @method('DELETE')
                        <button type="submit" class="dropdown-item text-danger f-12">
                            <i data-feather="trash-2" style="width:12px;height:12px;"></i> Smazat
                        </button>
                    </form>
                </li>
            </ul>
        </div>
    </div>

    @if($task->description)
        <p class="card-desc">{{ Str::limit($task->description, 80) }}</p>
    @endif

    <div class="d-flex flex-wrap gap-1 align-items-center mt-1">
        <span class="badge badge-light-{{ $task->priorityColor() }} f-10">{{ $task->priorityLabel() }}</span>

        @if($task->assignee)
            <span class="badge badge-light-secondary f-10">
                <i data-feather="user" style="width:9px;height:9px;"></i>
                {{ $task->assignee->name }}
            </span>
        @endif

        @if($task->due_date)
            <span class="f-10 ms-auto {{ $task->isOverdue() ? 'overdue-chip' : 'f-light' }}">
                @if($task->isOverdue())
                    <i data-feather="alert-circle" style="width:9px;height:9px;"></i>
                @endif
                {{ $task->due_date->format('d.m.') }}
            </span>
        @endif
    </div>
</div>
