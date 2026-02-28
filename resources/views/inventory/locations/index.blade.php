@extends('layouts.master')

@section('title', 'Stock Locations')

@section('content')
<div class="main-content">
    <div class="page-content">
        <div class="container-fluid">

            <!-- PAGE TITLE -->
            <div class="row">
                <div class="col-12">
                    <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                        <h4 class="mb-sm-0">{{ $pagetitle ?? 'Stock Locations' }}</h4>
                        <div class="page-title-right">
                            <ol class="breadcrumb m-0">
                                <li class="breadcrumb-item"><a href="{{ route('inventory.index') }}">Inventory</a></li>
                                <li class="breadcrumb-item active">Locations</li>
                            </ol>
                        </div>
                    </div>
                </div>
            </div>

            <!-- LOCATIONS MANAGEMENT -->
            <div class="row">
                <div class="col-lg-12">
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="card-title mb-0">Stock Locations ({{ $locations->count() }})</h5>
                            @can('Manage stock locations')
                            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addLocationModal">
                                <i class="bi bi-plus-circle me-1"></i> Add Location
                            </button>
                            @endcan
                        </div>
                        <div class="card-body">
                            <!-- SEARCH AND FILTER BAR -->
                            <div class="row mb-3">
                                <div class="col-md-8">
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="bi bi-search"></i></span>
                                        <input type="text" id="searchInput" class="form-control" placeholder="Search locations by name, code, address, or contact...">
                                        <button class="btn btn-outline-secondary" type="button" id="clearSearch">
                                            <i class="bi bi-x-circle"></i>
                                        </button>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="d-flex gap-2">
                                        <select id="statusFilter" class="form-select form-select-sm">
                                            <option value="">All Status</option>
                                            <option value="active">Active</option>
                                            <option value="inactive">Inactive</option>
                                        </select>
                                        <select id="defaultFilter" class="form-select form-select-sm">
                                            <option value="">All Locations</option>
                                            <option value="default">Default Only</option>
                                            <option value="non-default">Non-default</option>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <!-- RESULTS INFO -->
                            <div class="row mb-3">
                                <div class="col-12">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div id="resultsInfo" class="text-muted">
                                            Showing {{ $locations->count() }} locations
                                        </div>
                                        <div class="d-flex gap-2">
                                            <button class="btn btn-outline-secondary btn-sm" id="exportCsv">
                                                <i class="bi bi-download me-1"></i> Export
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            @if($locations->count() > 0)
                                <div class="table-responsive" id="locationsTableContainer">
                                    <table class="table table-centered align-middle table-nowrap mb-0" id="locationsTable">
                                        <thead class="table-light">
                                            <tr>
                                                <th width="5%">
                                                    <a href="#" class="sortable-header" data-sort="id">
                                                        # <i class="bi bi-arrow-down-up"></i>
                                                    </a>
                                                </th>
                                                <th>
                                                    <a href="#" class="sortable-header" data-sort="name">
                                                        Name <i class="bi bi-arrow-down-up"></i>
                                                    </a>
                                                </th>
                                                <th>
                                                    <a href="#" class="sortable-header" data-sort="code">
                                                        Code <i class="bi bi-arrow-down-up"></i>
                                                    </a>
                                                </th>
                                                <th>Address</th>
                                                <th>Contact</th>
                                                <th>
                                                    <a href="#" class="sortable-header" data-sort="status">
                                                        Status <i class="bi bi-arrow-down-up"></i>
                                                    </a>
                                                </th>
                                                <th>Default</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody id="locationsTableBody">
                                            @foreach($locations as $location)
                                                <tr class="location-row"
                                                    data-name="{{ strtolower($location->name) }}"
                                                    data-code="{{ strtolower($location->code ?? '') }}"
                                                    data-address="{{ strtolower($location->address ?? '') }}"
                                                    data-contact="{{ strtolower($location->contact_person ?? '') }}"
                                                    data-status="{{ $location->is_active ? 'active' : 'inactive' }}"
                                                    data-default="{{ $location->is_default ? 'default' : 'non-default' }}">
                                                    <td>{{ $loop->iteration }}</td>
                                                    <td>
                                                        <div class="fw-semibold">{{ $location->name }}</div>
                                                        @if($location->notes)
                                                            <small class="text-muted">{{ Str::limit($location->notes, 50) }}</small>
                                                        @endif
                                                    </td>
                                                    <td>{{ $location->code ?? 'N/A' }}</td>
                                                    <td>{{ Str::limit($location->address, 50) ?? 'N/A' }}</td>
                                                    <td>
                                                        @if($location->contact_person)
                                                            <div>{{ $location->contact_person }}</div>
                                                            <small class="text-muted">{{ $location->phone }}</small>
                                                        @else
                                                            N/A
                                                        @endif
                                                    </td>
                                                    <td>
                                                        <span class="badge bg-{{ $location->is_active ? 'success' : 'danger' }}">
                                                            {{ $location->is_active ? 'Active' : 'Inactive' }}
                                                        </span>
                                                    </td>
                                                    <td>
                                                        @if($location->is_default)
                                                            <span class="badge bg-primary">Default</span>
                                                        @else
                                                            <span class="text-muted">-</span>
                                                        @endif
                                                    </td>
                                                    <td>
                                                        <div class="dropdown">
                                                            <button class="btn btn-subtle-secondary btn-sm btn-icon" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                                                <i class="bi bi-three-dots-vertical"></i>
                                                            </button>
                                                            <ul class="dropdown-menu dropdown-menu-end">
                                                                <li>
                                                                    <a class="dropdown-item view-location-btn" href="javascript:void(0);" data-id="{{ $location->id }}">
                                                                        <i class="bi bi-eye me-2"></i> View Details
                                                                    </a>
                                                                </li>
                                                                @can('Manage stock locations')
                                                                <li>
                                                                    <a class="dropdown-item edit-location-btn" href="javascript:void(0);" data-id="{{ $location->id }}">
                                                                        <i class="bi bi-pencil me-2"></i> Edit
                                                                    </a>
                                                                </li>
                                                                @if(!$location->is_default)
                                                                <li>
                                                                    <a class="dropdown-item text-danger delete-location-btn" href="javascript:void(0);" data-id="{{ $location->id }}">
                                                                        <i class="bi bi-trash me-2"></i> Delete
                                                                    </a>
                                                                </li>
                                                                @endif
                                                                @endcan
                                                            </ul>
                                                        </div>
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>

                                <!-- PAGINATION (for server-side implementation) -->
                                @if($locations instanceof \Illuminate\Pagination\LengthAwarePaginator && $locations->hasPages())
                                <div class="row mt-3">
                                    <div class="col-12">
                                        <nav aria-label="Page navigation">
                                            <ul class="pagination pagination-sm justify-content-end mb-0">
                                                {{ $locations->links() }}
                                            </ul>
                                        </nav>
                                    </div>
                                </div>
                                @endif

                                <!-- NO RESULTS MESSAGE (hidden by default) -->
                                <div id="noResults" class="text-center py-5 text-muted d-none">
                                    <i class="bi bi-search fs-1"></i>
                                    <p class="mt-2">No locations found matching your search</p>
                                    <button class="btn btn-outline-primary mt-2" id="clearFiltersBtn">
                                        Clear all filters
                                    </button>
                                </div>

                            @else
                                <div class="text-center py-5 text-muted">
                                    <i class="bi bi-inbox fs-1"></i>
                                    <p class="mt-2">No stock locations found</p>
                                    @can('Manage stock locations')
                                    <button type="button" class="btn btn-primary mt-2" data-bs-toggle="modal" data-bs-target="#addLocationModal">
                                        <i class="bi bi-plus-circle me-1"></i> Add Your First Location
                                    </button>
                                    @endcan
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>

<!-- Add your existing modals here (addLocationModal, editLocationModal, viewLocationModal) -->

<script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    console.log('DOM loaded - Initializing stock locations script');

    // Search and Filter Variables
    let currentSearch = '';
    let currentStatusFilter = '';
    let currentDefaultFilter = '';
    let currentSort = {
        field: 'id',
        direction: 'asc'
    };

    // DOM Elements
    const searchInput = document.getElementById('searchInput');
    const clearSearchBtn = document.getElementById('clearSearch');
    const statusFilter = document.getElementById('statusFilter');
    const defaultFilter = document.getElementById('defaultFilter');
    const clearFiltersBtn = document.getElementById('clearFiltersBtn');
    const locationsTableBody = document.getElementById('locationsTableBody');
    const locationsTable = document.getElementById('locationsTable');
    const noResultsDiv = document.getElementById('noResults');
    const resultsInfo = document.getElementById('resultsInfo');
    const exportCsvBtn = document.getElementById('exportCsv');

    // Initialize DataTable functionality
    function initializeDataTable() {
        console.log('Initializing data table functionality');

        // Add event listeners for search
        if (searchInput) {
            searchInput.addEventListener('input', debounce(function() {
                currentSearch = this.value.toLowerCase().trim();
                filterAndSortLocations();
            }, 300));
        }

        // Clear search button
        if (clearSearchBtn) {
            clearSearchBtn.addEventListener('click', function() {
                if (searchInput) {
                    searchInput.value = '';
                    currentSearch = '';
                    filterAndSortLocations();
                }
            });
        }

        // Status filter
        if (statusFilter) {
            statusFilter.addEventListener('change', function() {
                currentStatusFilter = this.value;
                filterAndSortLocations();
            });
        }

        // Default location filter
        if (defaultFilter) {
            defaultFilter.addEventListener('change', function() {
                currentDefaultFilter = this.value;
                filterAndSortLocations();
            });
        }

        // Clear all filters button
        if (clearFiltersBtn) {
            clearFiltersBtn.addEventListener('click', function() {
                resetFilters();
            });
        }

        // Sortable headers
        const sortableHeaders = document.querySelectorAll('.sortable-header');
        sortableHeaders.forEach(header => {
            header.addEventListener('click', function(e) {
                e.preventDefault();
                const sortField = this.dataset.sort;
                toggleSort(sortField);
            });
        });

        // Export CSV button
        if (exportCsvBtn) {
            exportCsvBtn.addEventListener('click', exportToCSV);
        }

        console.log('Data table functionality initialized');
    }

    // Debounce function for search input
    function debounce(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func.apply(this, args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    }

    // Filter and sort locations
    function filterAndSortLocations() {
        const rows = document.querySelectorAll('.location-row');
        let visibleCount = 0;

        rows.forEach(row => {
            const name = row.dataset.name || '';
            const code = row.dataset.code || '';
            const address = row.dataset.address || '';
            const contact = row.dataset.contact || '';
            const status = row.dataset.status || '';
            const isDefault = row.dataset.default || '';

            let matchesSearch = true;
            let matchesStatus = true;
            let matchesDefault = true;

            // Apply search filter
            if (currentSearch) {
                matchesSearch = name.includes(currentSearch) ||
                               code.includes(currentSearch) ||
                               address.includes(currentSearch) ||
                               contact.includes(currentSearch);
            }

            // Apply status filter
            if (currentStatusFilter) {
                matchesStatus = status === currentStatusFilter;
            }

            // Apply default filter
            if (currentDefaultFilter) {
                matchesDefault = isDefault === currentDefaultFilter;
            }

            // Show/hide row based on filters
            if (matchesSearch && matchesStatus && matchesDefault) {
                row.style.display = '';
                visibleCount++;
            } else {
                row.style.display = 'none';
            }
        });

        // Sort visible rows
        sortVisibleRows(rows);

        // Update results info
        updateResultsInfo(visibleCount);

        // Show/hide no results message
        if (noResultsDiv) {
            if (visibleCount === 0 && rows.length > 0) {
                noResultsDiv.classList.remove('d-none');
                if (locationsTable) {
                    locationsTable.style.display = 'none';
                }
            } else {
                noResultsDiv.classList.add('d-none');
                if (locationsTable) {
                    locationsTable.style.display = '';
                }
            }
        }
    }

    // Sort visible rows
    function sortVisibleRows(rows) {
        const visibleRows = Array.from(rows).filter(row => row.style.display !== 'none');

        visibleRows.sort((a, b) => {
            let aValue, bValue;

            switch (currentSort.field) {
                case 'name':
                    aValue = a.querySelector('td:nth-child(2) .fw-semibold')?.textContent || '';
                    bValue = b.querySelector('td:nth-child(2) .fw-semibold')?.textContent || '';
                    break;
                case 'code':
                    aValue = a.querySelector('td:nth-child(3)')?.textContent || '';
                    bValue = b.querySelector('td:nth-child(3)')?.textContent || '';
                    break;
                case 'status':
                    aValue = a.querySelector('td:nth-child(6) .badge')?.textContent || '';
                    bValue = b.querySelector('td:nth-child(6) .badge')?.textContent || '';
                    break;
                default: // id
                    aValue = parseInt(a.querySelector('td:nth-child(1)')?.textContent || 0);
                    bValue = parseInt(b.querySelector('td:nth-child(1)')?.textContent || 0);
            }

            // Convert to lowercase for string comparison
            if (typeof aValue === 'string') {
                aValue = aValue.toLowerCase();
                bValue = bValue.toLowerCase();
            }

            if (currentSort.direction === 'asc') {
                return aValue > bValue ? 1 : -1;
            } else {
                return aValue < bValue ? 1 : -1;
            }
        });

        // Update table order
        const tbody = document.querySelector('#locationsTable tbody');
        if (tbody) {
            // Remove existing rows
            rows.forEach(row => tbody.removeChild(row));

            // Add sorted rows back
            visibleRows.forEach((row, index) => {
                // Update the serial number
                const serialCell = row.querySelector('td:nth-child(1)');
                if (serialCell) {
                    serialCell.textContent = index + 1;
                }
                tbody.appendChild(row);
            });
        }

        // Update sort indicators
        updateSortIndicators();
    }

    // Toggle sort field and direction
    function toggleSort(field) {
        if (currentSort.field === field) {
            // Toggle direction
            currentSort.direction = currentSort.direction === 'asc' ? 'desc' : 'asc';
        } else {
            // New field, default to asc
            currentSort.field = field;
            currentSort.direction = 'asc';
        }

        filterAndSortLocations();
    }

    // Update sort indicators in headers
    function updateSortIndicators() {
        const headers = document.querySelectorAll('.sortable-header');
        headers.forEach(header => {
            const icon = header.querySelector('i');
            const field = header.dataset.sort;

            if (icon) {
                // Reset all icons
                icon.className = 'bi bi-arrow-down-up';

                // Set active sort indicator
                if (field === currentSort.field) {
                    icon.className = currentSort.direction === 'asc'
                        ? 'bi bi-arrow-up'
                        : 'bi bi-arrow-down';
                }
            }
        });
    }

    // Update results information
    function updateResultsInfo(visibleCount) {
        if (resultsInfo) {
            const totalRows = document.querySelectorAll('.location-row').length;
            let infoText = `Showing ${visibleCount} of ${totalRows} locations`;

            if (currentSearch || currentStatusFilter || currentDefaultFilter) {
                infoText += ' (filtered)';

                // Add active filters info
                const activeFilters = [];
                if (currentSearch) activeFilters.push(`search: "${currentSearch}"`);
                if (currentStatusFilter) activeFilters.push(`status: ${currentStatusFilter}`);
                if (currentDefaultFilter) activeFilters.push(`default: ${currentDefaultFilter}`);

                if (activeFilters.length > 0) {
                    infoText += ` - Filters: ${activeFilters.join(', ')}`;
                }
            }

            resultsInfo.textContent = infoText;
        }
    }

    // Reset all filters
    function resetFilters() {
        if (searchInput) {
            searchInput.value = '';
            currentSearch = '';
        }

        if (statusFilter) {
            statusFilter.value = '';
            currentStatusFilter = '';
        }

        if (defaultFilter) {
            defaultFilter.value = '';
            currentDefaultFilter = '';
        }

        currentSort = {
            field: 'id',
            direction: 'asc'
        };

        filterAndSortLocations();
        updateSortIndicators();
    }

    // Export to CSV function
    function exportToCSV() {
        const visibleRows = document.querySelectorAll('.location-row');
        const csvData = [];

        // Add header row
        csvData.push(['#', 'Name', 'Code', 'Address', 'Contact', 'Status', 'Default', 'Notes']);

        // Add data rows
        visibleRows.forEach(row => {
            if (row.style.display !== 'none') {
                const cells = row.querySelectorAll('td');
                const rowData = [
                    cells[0].textContent,
                    cells[1].querySelector('.fw-semibold')?.textContent || '',
                    cells[2].textContent,
                    cells[3].textContent,
                    cells[4].querySelector('div')?.textContent || '',
                    cells[5].querySelector('.badge')?.textContent || '',
                    cells[6].querySelector('.badge')?.textContent || cells[6].textContent,
                    cells[1].querySelector('small')?.textContent || ''
                ];
                csvData.push(rowData);
            }
        });

        // Convert to CSV string
        const csvContent = csvData.map(row =>
            row.map(cell => {
                // Escape quotes and wrap in quotes if contains comma or quotes
                const cellStr = String(cell).replace(/"/g, '""');
                return /[,"\n]/.test(cellStr) ? `"${cellStr}"` : cellStr;
            }).join(',')
        ).join('\n');

        // Create and download file
        const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
        const link = document.createElement('a');
        const url = URL.createObjectURL(blob);
        link.setAttribute('href', url);
        link.setAttribute('download', `stock_locations_${new Date().toISOString().split('T')[0]}.csv`);
        link.style.visibility = 'hidden';
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);

        // Show success message
        Swal.fire({
            icon: 'success',
            title: 'Exported Successfully',
            text: `Exported ${csvData.length - 1} locations to CSV file`,
            timer: 2000,
            showConfirmButton: false
        });
    }

    // Refresh table after CRUD operations
    window.refreshLocationsTable = function() {
        console.log('Refreshing locations table...');

        // This could be enhanced to fetch fresh data from server
        // For now, we'll just reset filters and show all data
        resetFilters();

        // Or if you want server-side refresh:
        // axios.get('{{ route("stock-locations.index") }}')
        //     .then(response => {
        //         // Update table with new data
        //         // This would require more complex implementation
        //     });
    };

    // Initialize everything
    initializeDataTable();

    // Add refresh function to window for access from modals
    window.refreshTable = refreshLocationsTable;

    console.log('Stock locations script with search functionality initialized successfully');
});

// Add this function to your existing JavaScript for CRUD operations
// In your add/edit/delete success callbacks, call: window.refreshTable();

</script>

<!-- Add your existing CRUD JavaScript code here -->

@endsection
