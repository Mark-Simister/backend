@extends('layouts.admin.master')

@section('title', 'Edit Permissions for ' . $role->name)

@push('style')
<style>
    .card { border-radius: 16px; }
    .card-header { border-top-left-radius: 16px; border-top-right-radius: 16px; }
    .card-shadow { box-shadow: 0 8px 22px rgba(0,0,0,.06); }
    .toolbar .btn { min-width: 140px; }
    .group-title { letter-spacing: .3px; font-weight: 600; text-transform: capitalize; }
    .selected-pill { font-size: .85rem; }
    .form-check-input { width: 1.2rem; height: 1.2rem; }
    .permission-group .form-check { padding: .4rem .6rem; border-radius: 8px; background: #fff; }
    .permission-group .form-check:hover { background: #f8f9fa; }
    .sticky-toolbar { position: sticky; top: .5rem; z-index: 1030; }
</style>
@endpush

@section('content')
@php
    // Group by module (text before the dot)
    $grouped = $permissions->groupBy(function ($p) {
        $parts = explode('.', $p->name, 2);
        return $parts[0] ?? $p->name;
    });

    // Sort actions inside a module using this order
    $actionOrder = ['view','create','edit','delete','publish','approve','reject'];

    // Nicify label: turn "character_tag" -> "Character Tag", action part ucwords
    $nicify = function (string $text) {
        return ucwords(str_replace('_', ' ', $text));
    };
@endphp

<div class="container-fluid">
    <div class="row justify-content-center">
        <div class="col-12">

            <div class="card shadow-lg border-0 card-shadow mb-4">
                <div class="card-header bg-primary text-white">
                    <h4 class="mb-0">Edit Permissions for <strong>{{ $role->name }}</strong></h4>
                </div>
                <div class="card-body">

                    <form action="{{ route('admin.roles.updatePermissions', $role) }}" method="POST" id="permForm">
                        @csrf
                        @method('PUT')

                        <!-- Global toolbar -->
                        <div class="sticky-toolbar toolbar d-flex flex-wrap gap-2 align-items-center mb-3">
                            <button type="button" id="btnSelectAll" class="btn btn-outline-primary btn-lg">
                                Select All
                            </button>
                            <button type="button" id="btnDeselectAll" class="btn btn-outline-secondary btn-lg">
                                Deselect All
                            </button>
                            <button type="button" id="btnReset" class="btn btn-outline-danger btn-lg">
                                Reset
                            </button>

                            <div class="ms-auto d-flex align-items-center gap-2">
                                <input type="search" id="permSearch" class="form-control" placeholder="Search permissions or groups…">
                            </div>
                        </div>

                        <!-- Permission groups grid -->
                        <div class="row g-3">
                            @foreach($grouped as $group => $groupPerms)
                                @php
                                    $sorted = $groupPerms->sortBy(function($p) use ($actionOrder) {
                                        $parts = explode('.', $p->name, 2);
                                        $action = $parts[1] ?? '';
                                        $idx = array_search($action, $actionOrder, true);
                                        return $idx === false ? 999 : $idx;
                                    });
                                @endphp

                                <div class="col-xl-4 col-lg-6 col-md-6 permission-group" data-group="{{ $group }}">
                                    <div class="card h-100 card-shadow border-0">
                                        <div class="card-header bg-light d-flex justify-content-between align-items-center">
                                            <div class="group-title">
                                                {{ $nicify($group) }}
                                                <span class="badge bg-secondary selected-pill ms-2">
                                                    <span class="selected-count">0</span> / {{ $sorted->count() }}
                                                </span>
                                            </div>
                                            <div class="btn-group btn-group-sm" role="group" aria-label="Group actions">
                                                <button type="button" class="btn btn-outline-success group-select-all" data-group="{{ $group }}">All</button>
                                                <button type="button" class="btn btn-outline-warning group-deselect-all" data-group="{{ $group }}">None</button>
                                            </div>
                                        </div>
                                        <div class="card-body">
                                            <div class="row g-2">
                                                @foreach($sorted as $permission)
                                                    @php
                                                        $parts = explode('.', $permission->name, 2);
                                                        $action = $parts[1] ?? $permission->name;
                                                    @endphp
                                                    <div class="col-12">
                                                        <div class="form-check">
                                                            <input
                                                                type="checkbox"
                                                                id="perm-{{ $permission->id }}"
                                                                name="permissions[]"
                                                                value="{{ $permission->id }}"
                                                                class="form-check-input perm-checkbox"
                                                                data-group="{{ $group }}"
                                                                @checked(in_array($permission->id, $rolePermissions))
                                                            >
                                                            <label class="form-check-label" for="perm-{{ $permission->id }}">
                                                                {{ $nicify($action) }}
                                                            </label>
                                                        </div>
                                                    </div>
                                                @endforeach
                                            </div>
                                        </div>
                                    </div>
                                </div>

                            @endforeach
                        </div>

                        <div class="text-center mt-4">
                            <button class="btn btn-success btn-lg px-4 py-2">Update Permissions</button>
                        </div>
                    </form>

                </div>
            </div>

        </div>
    </div>
</div>

@push('scripts')
<script>
$(function () {

  const $doc = $(document);
  const $groups = $('.permission-group');
  const $checkboxes = $('.perm-checkbox');

  // Save initial state for Reset
  const initialState = {};
  $checkboxes.each(function () {
    const $cb = $(this);
    const key = $cb.attr('id') || $cb.val(); // fallback if no id
    initialState[key] = $cb.prop('checked');
  });

  function updateGroupCounts() {
    $groups.each(function () {
      const $g = $(this);
      const selected = $g.find('.perm-checkbox:checked').length;
      $g.find('.selected-count').text(selected);
    });
  }

  // Initial + on change
  updateGroupCounts();
  $doc.on('change', '.perm-checkbox', updateGroupCounts);

  // Global actions
  $doc.on('click', '#btnSelectAll', function (e) {
    e.preventDefault();
    $('.perm-checkbox').prop('checked', true).trigger('change');
  });

  $doc.on('click', '#btnDeselectAll', function (e) {
    e.preventDefault();
    $('.perm-checkbox').prop('checked', false).trigger('change');
  });

  $doc.on('click', '#btnReset', function (e) {
    e.preventDefault();
    $('.perm-checkbox').each(function () {
      const $cb = $(this);
      const key = $cb.attr('id') || $cb.val();
      $cb.prop('checked', !!initialState[key]);
    }).trigger('change');
  });

  // Per-group actions
  $doc.on('click', '.group-select-all', function (e) {
    e.preventDefault();
    const group = $(this).data('group');
    $('.perm-checkbox[data-group="' + group + '"]').prop('checked', true).trigger('change');
  });

  $doc.on('click', '.group-deselect-all', function (e) {
    e.preventDefault();
    const group = $(this).data('group');
    $('.perm-checkbox[data-group="' + group + '"]').prop('checked', false).trigger('change');
  });

  // Live search
  $('#permSearch').on('input', function () {
    const q = $(this).val().toLowerCase().trim();
    $('.permission-group').each(function () {
      const $g = $(this);
      const groupName = String($g.data('group') || '').toLowerCase().replace(/_/g, ' ');
      const labels = $g.find('.form-check-label').map(function () { return $(this).text().toLowerCase(); }).get();
      const match = !q || groupName.includes(q) || labels.some(t => t.includes(q));
      $g.toggle(match);
    });
  });
});
</script>


@endpush
@endsection
