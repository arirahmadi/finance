<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Keuangan - Finance System</title>
    <link rel="stylesheet" href="{{ asset('css/dashboard.css') }}?v=1.0.5">
    <script>
        // Init theme from localStorage (default: dark/true black)
        const savedTheme = localStorage.getItem('theme') || 'dark';
        document.documentElement.setAttribute('data-theme', savedTheme);
    </script>
</head>
<body>
    <!-- Early-defined dismiss function so onclick works before main script loads -->
    <script>
        function dismissAlert() {
            var alert = document.getElementById('flashAlert');
            if (!alert) return;
            alert.classList.add('dismissing');
            setTimeout(function() { if (alert.parentNode) alert.parentNode.removeChild(alert); }, 320);
        }
    </script>

    <div class="dashboard-container">
        <!-- Flash Alert Messages -->
        @if (session('success'))
            <div class="alert alert-success" id="flashAlert" role="alert">
                <svg style="width:18px;height:18px;flex-shrink:0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                </svg>
                <span>{{ session('success') }}</span>
                <button class="alert-dismiss-btn" onclick="dismissAlert()" aria-label="Tutup">&times;</button>
            </div>
        @endif
        @if ($errors->any())
            <div class="alert alert-danger" id="flashAlert" role="alert">
                <svg style="width:18px;height:18px;flex-shrink:0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v4m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z" />
                </svg>
                <span>
                @foreach ($errors->all() as $error)
                    {{ $error }}<br>
                @endforeach
                </span>
                <button class="alert-dismiss-btn" onclick="dismissAlert()" aria-label="Tutup">&times;</button>
            </div>
        @endif

        <!-- ========== FLOATING SIDEBAR OVERLAY ========== -->

        <!-- Backdrop (semi-transparent overlay behind sidebar) -->
        <div class="sidebar-backdrop" id="sidebarBackdrop" onclick="closeSidebar()"></div>

        <!-- Floating MENU Pill Button (always visible, top-left) -->
        <button class="menu-pill-btn" id="menuPillBtn" onclick="toggleSidebar()">
            <span class="menu-pill-icon" id="menuPillIcon">
                <svg style="width:16px;height:16px;" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M4 6h16M4 12h16M4 18h16"/>
                </svg>
            </span>
            <span class="menu-pill-text" id="menuPillText">MENU</span>
            <span class="menu-pill-plus" id="menuPillPlus">+</span>
        </button>


        <!-- Floating Sidebar Drawer -->
        <aside class="sidebar-drawer glass-panel" id="sidebarDrawer">
            <!-- No close button here — use floating MENU/CLOSE pill above -->

            <div class="sidebar-brand-drawer">Finance System</div>

            <div class="sidebar-profile-drawer">
                <div class="profile-avatar-drawer">
                    {{ strtoupper(substr(Auth::user()->name, 0, 1)) }}
                </div>
                <div>
                    <div class="profile-name-drawer">{{ Auth::user()->name }}</div>
                    <div class="role-badge role-badge-{{ Auth::user()->role }}">
                        {{ Auth::user()->role === 'owner' ? 'Owner' : 'Staff Finance' }}
                    </div>
                </div>
            </div>

            <nav class="sidebar-nav-drawer">
                <div onclick="switchTab('dashboard'); closeSidebar();" id="nav-dashboard" class="sidebar-nav-item active">
                    <svg style="width:22px;height:22px;flex-shrink:0;" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v4a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v4a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v4a2 2 0 01-2 2H6a2 2 0 01-2-2v-4zM14 16a2 2 0 012-2h2a2 2 0 012 2v4a2 2 0 01-2 2h-2a2 2 0 01-2-2v-4z" />
                    </svg>
                    <span>Dashboard</span>
                </div>
                @if (Auth::user()->hasPermission('view_transactions'))
                    <div onclick="switchTab('transactions'); closeSidebar();" id="nav-transactions" class="sidebar-nav-item">
                        <svg style="width:22px;height:22px;flex-shrink:0;" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01" />
                        </svg>
                        <span>Transaksi</span>
                    </div>
                @endif

                @if (Auth::user()->hasPermission('view_transactions'))
                    <div onclick="switchTab('ledger'); closeSidebar();" id="nav-ledger" class="sidebar-nav-item">
                        <svg style="width:22px;height:22px;flex-shrink:0;" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
                        </svg>
                        <span>Buku Besar</span>
                    </div>
                @endif

                @if (Auth::user()->role === 'owner')
                    <div onclick="switchTab('users'); closeSidebar();" id="nav-users" class="sidebar-nav-item">
                        <svg style="width:22px;height:22px;flex-shrink:0;" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                        </svg>
                        <span>User & Roles</span>
                    </div>
                    <div onclick="switchTab('settings'); closeSidebar();" id="nav-settings" class="sidebar-nav-item">
                        <svg style="width:22px;height:22px;flex-shrink:0;" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                        </svg>
                        <span>Setting (COA)</span>
                    </div>
                @endif
            </nav>

            <div style="margin-top: auto; padding-top: 24px; border-top: 1px solid var(--border-glass);">
                <form action="{{ route('logout') }}" method="POST">
                    @csrf
                    <button type="submit" class="sidebar-logout-btn">
                        <svg style="width:18px;height:18px;flex-shrink:0;" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                        </svg>
                        <span>Keluar</span>
                    </button>
                </form>
            </div>
        </aside>

        <!-- ========== MAIN CONTENT (Full Width) ========== -->
        <div class="dashboard-fullwidth">
            <!-- Header -->
            <header class="dashboard-header glass-panel" style="padding: 20px 30px; border-radius: 16px;">
                <div class="dashboard-brand">
                    <h1 id="content-title">Dashboard</h1>
                    <p style="color: var(--text-secondary); font-size: 0.85rem;">Startup Financial Control Center</p>
                </div>
                <div class="dashboard-user">
                    <!-- Theme Toggle Button -->
                    <button type="button" id="themeToggleBtn" class="theme-toggle-btn" title="Ubah Tema">
                        <!-- Dynamic SVG icon -->
                    </button>
                    <div class="user-info">
                        <div class="user-name">{{ Auth::user()->name }}</div>
                        <div class="user-role">{{ Auth::user()->role === 'owner' ? 'Owner' : 'Staff Finance' }}</div>
                    </div>
                </div>
            </header>

            <!-- Section: Dashboard Overview Tab -->
            <div id="section-dashboard" class="tab-section">

                    <!-- KPI Grid -->
                    <section class="kpi-grid">
                        <!-- Total Inflow -->
                        <div class="kpi-card glass-panel kpi-inflow">
                            <div class="kpi-title">Total Uang Masuk</div>
                            <div class="kpi-value amount-in">Rp {{ number_format($summary->total_in, 0, ',', '.') }}</div>
                            <div class="kpi-desc">Pendapatan operasional & investasi masuk</div>
                        </div>

                        <!-- Total Outflow -->
                        <div class="kpi-card glass-panel kpi-outflow">
                            <div class="kpi-title">Total Uang Keluar</div>
                            <div class="kpi-value amount-out">Rp {{ number_format($summary->total_out, 0, ',', '.') }}</div>
                            <div class="kpi-desc">Pengeluaran beban & operasional kantor</div>
                        </div>

                        <!-- Net Flow -->
                        <div class="kpi-card glass-panel kpi-balance">
                            <div class="kpi-title">Saldo Akhir (Net Flow)</div>
                            <div class="kpi-value" style="color: {{ $summary->net_flow >= 0 ? '#60a5fa' : 'var(--color-danger)' }}">
                                Rp {{ number_format($summary->net_flow, 0, ',', '.') }}
                            </div>
                            <div class="kpi-desc">Sisa kas yang tersedia saat ini</div>
                        </div>
                    </section>

                    <!-- Transferred-Only Summary Widgets -->
                    <section class="kpi-grid" style="margin-top: 24px;">
                        <!-- Transaksi Ditransfer -->
                        <div class="kpi-card glass-panel" style="border-left: 4px solid #60a5fa;">
                            <div class="kpi-title" style="display: flex; align-items: center; gap: 8px;">
                                <svg style="width:18px;height:18px;color:#60a5fa;flex-shrink:0;" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                                </svg>
                                Transaksi Ditransfer
                            </div>
                            <div class="kpi-value" style="font-size: 1.1rem; color: var(--text-primary);">
                                {{ $dashboardWidgets->transactions->count }} transaksi
                            </div>
                            <div style="display: flex; gap: 16px; margin-top: 8px; font-size: 0.8rem;">
                                <div>
                                    <span style="color: var(--text-secondary);">Masuk:</span>
                                    <span class="amount-in" style="font-weight: 600;">Rp {{ number_format($dashboardWidgets->transactions->total_in, 0, ',', '.') }}</span>
                                </div>
                                <div>
                                    <span style="color: var(--text-secondary);">Keluar:</span>
                                    <span class="amount-out" style="font-weight: 600;">Rp {{ number_format($dashboardWidgets->transactions->total_out, 0, ',', '.') }}</span>
                                </div>
                            </div>
                            <div class="kpi-desc">Transaksi reguler yang sudah ditransfer</div>
                        </div>

                        <!-- Settlement Ditransfer -->
                        <div class="kpi-card glass-panel" style="border-left: 4px solid #a78bfa;">
                            <div class="kpi-title" style="display: flex; align-items: center; gap: 8px;">
                                <svg style="width:18px;height:18px;color:#a78bfa;flex-shrink:0;" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                                </svg>
                                Settlement Ditransfer
                            </div>
                            <div class="kpi-value" style="font-size: 1.1rem; color: var(--text-primary);">
                                {{ $dashboardWidgets->settlements->count }} settlement
                            </div>
                            <div style="margin-top: 8px; font-size: 0.8rem;">
                                <span style="color: var(--text-secondary);">Total Dana:</span>
                                <span class="amount-out" style="font-weight: 600;">Rp {{ number_format($dashboardWidgets->settlements->total, 0, ',', '.') }}</span>
                            </div>
                            <div class="kpi-desc">Uang muka karyawan yang sudah ditransfer</div>
                        </div>

                        <!-- Cash Advance Ditransfer -->
                        <div class="kpi-card glass-panel" style="border-left: 4px solid #fbbf24;">
                            <div class="kpi-title" style="display: flex; align-items: center; gap: 8px;">
                                <svg style="width:18px;height:18px;color:#fbbf24;flex-shrink:0;" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                                Cash Advance Ditransfer
                            </div>
                            <div class="kpi-value" style="font-size: 1.1rem; color: var(--text-primary);">
                                {{ $dashboardWidgets->cash_advances->count }} pinjaman
                            </div>
                            <div style="margin-top: 8px; font-size: 0.8rem;">
                                <span style="color: var(--text-secondary);">Total Ditransfer:</span>
                                <span style="font-weight: 600; color: #fbbf24;">Rp {{ number_format($dashboardWidgets->cash_advances->total, 0, ',', '.') }}</span>
                            </div>
                            <div class="kpi-desc">Nominal riil yang ditransfer ke karyawan</div>
                        </div>
                    </section>
                </div>

                <!-- Section: Transactions List Tab -->
                <div id="section-transactions" class="tab-section" style="display: none;">
                    <!-- Chrome-style tab container -->
                    <div class="chrome-tab-container">
                        <!-- Sub-tab Bar -->
                        <div class="sub-tab-bar no-print">
                            <button type="button" class="sub-tab-item active" id="sub-nav-transactions" onclick="switchSubTab('transactions')">
                                <svg class="sub-tab-icon" width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01" />
                                </svg>
                                <span>Transaksi</span>
                            </button>
                            @if (Auth::user()->hasPermission('view_settlements'))
                                <button type="button" class="sub-tab-item" id="sub-nav-settlements" onclick="switchSubTab('settlements')">
                                    <svg class="sub-tab-icon" width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                                    </svg>
                                    <span>Settlement</span>
                                </button>
                            @endif
                            @if (Auth::user()->hasPermission('view_cash_advances'))
                                <button type="button" class="sub-tab-item" id="sub-nav-cash-advances" onclick="switchSubTab('cash-advances')">
                                    <svg class="sub-tab-icon" width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                    <span>Cash Advance</span>
                                </button>
                            @endif
                        </div>
                    </div>

                    <!-- Sub-panel: Transaksi -->
                    <div id="sub-section-transactions" class="sub-tab-panel active">
                        <!-- Date Filtering and Quick Action Bar -->
                        <div class="action-filter-bar">
                        <form action="{{ route('dashboard') }}" method="GET" class="filter-form">
                            <div class="form-group">
                                <label for="start_date" class="form-label" style="font-size: 0.75rem; margin-bottom: 4px;">Tanggal Mulai</label>
                                <input 
                                    type="date" 
                                    name="start_date" 
                                    id="start_date" 
                                    class="form-input" 
                                    value="{{ $summary->start_date }}"
                                    style="padding: 8px 12px; font-size: 0.85rem;"
                                >
                            </div>
                            <div class="form-group">
                                <label for="end_date" class="form-label" style="font-size: 0.75rem; margin-bottom: 4px;">Tanggal Akhir</label>
                                <input 
                                    type="date" 
                                    name="end_date" 
                                    id="end_date" 
                                    class="form-input" 
                                    value="{{ $summary->end_date }}"
                                    style="padding: 8px 12px; font-size: 0.85rem;"
                                >
                            </div>
                            <button type="submit" class="btn btn-primary btn-sm" style="height: 38px;">
                                Filter Laporan
                            </button>
                            @if ($summary->start_date || $summary->end_date)
                                <a href="{{ route('dashboard') }}" class="btn btn-secondary btn-sm" style="height: 38px;">
                                    Reset
                                </a>
                            @endif
                            <a href="{{ route('web.export.csv', request()->query()) }}" class="btn btn-secondary btn-sm" style="height: 38px; display: inline-flex; align-items: center; gap: 6px;">
                                <svg style="width: 16px; height: 16px;" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                </svg>
                                Ekspor Excel (CSV)
                            </a>
                            <button type="button" onclick="window.print()" class="btn btn-secondary btn-sm" style="height: 38px; display: inline-flex; align-items: center; gap: 6px;">
                                <svg style="width: 16px; height: 16px;" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                                </svg>
                                Cetak Laporan (PDF)
                            </button>
                        </form>

                        <div class="action-buttons">
                            <button onclick="openTransactionModal('in')" class="btn btn-success">
                                <span style="margin-right: 8px; font-size: 1.1rem; line-height: 1;">+</span> Tambah Uang Masuk
                            </button>
                            <button onclick="openTransactionModal('out')" class="btn btn-danger">
                                <span style="margin-right: 8px; font-size: 1.1rem; line-height: 1;">+</span> Tambah Uang Keluar
                            </button>
                        </div>
                    </div>

                    <!-- Widget Ringkasan per Kategori -->
                    @if (isset($categorySummary) && count($categorySummary) > 0)
                        <section class="glass-panel" style="margin-bottom: 24px; padding: 20px;">
                            <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 16px;">
                                <h3 style="font-size: 1.1rem; font-weight: 600; display: flex; align-items: center; gap: 8px;">
                                    <svg style="width: 20px; height: 20px; color: var(--color-primary);" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                                    </svg>
                                    Ringkasan per Kategori
                                </h3>
                                <span style="font-size: 0.75rem; color: var(--text-secondary); background: rgba(255, 255, 255, 0.05); padding: 4px 8px; border-radius: 6px; border: 1px solid var(--border-glass);">
                                    {{ count($categorySummary) }} Kategori Terdaftar
                                </span>
                            </div>
                            <div class="table-wrapper" style="overflow-x: auto; border: 1px solid var(--border-glass); border-radius: 12px; background: rgba(0,0,0,0.2);">
                                <table class="table" style="margin-bottom: 0;">
                                    <thead>
                                        <tr style="background: rgba(255, 255, 255, 0.02);">
                                            <th>Nama Kategori</th>
                                            <th style="text-align: right; width: 22%;">Uang Masuk</th>
                                            <th style="text-align: right; width: 25%;">Uang Keluar <span style="font-size: 0.7rem; color: var(--text-secondary); font-weight: normal; display: block;">(Sudah Ditransfer)</span></th>
                                            <th style="text-align: right; width: 28%;">Prakiraan Uang Keluar <span style="font-size: 0.7rem; color: var(--text-secondary); font-weight: normal; display: block;">(Belum Ditransfer)</span></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($categorySummary as $summary)
                                            @if ($summary->uang_masuk > 0 || $summary->uang_keluar_transfer > 0 || $summary->uang_keluar_prakiraan > 0)
                                                <tr>
                                                    <td style="font-weight: 500; color: var(--text-primary);">{{ $summary->category }}</td>
                                                    <td style="text-align: right;" class="amount-in">
                                                        @if ($summary->uang_masuk > 0)
                                                            + Rp {{ number_format($summary->uang_masuk, 0, ',', '.') }}
                                                        @else
                                                            <span style="color: var(--text-muted); font-weight: normal;">-</span>
                                                        @endif
                                                    </td>
                                                    <td style="text-align: right;" class="amount-out">
                                                        @if ($summary->uang_keluar_transfer > 0)
                                                            - Rp {{ number_format($summary->uang_keluar_transfer, 0, ',', '.') }}
                                                        @else
                                                            <span style="color: var(--text-muted); font-weight: normal;">-</span>
                                                        @endif
                                                    </td>
                                                    <td style="text-align: right; color: #fbbf24; font-weight: 600;">
                                                        @if ($summary->uang_keluar_prakiraan > 0)
                                                            - Rp {{ number_format($summary->uang_keluar_prakiraan, 0, ',', '.') }}
                                                        @else
                                                            <span style="color: var(--text-muted); font-weight: normal;">-</span>
                                                        @endif
                                                    </td>
                                                </tr>
                                            @endif
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </section>
                    @endif

                    <!-- Transactions Table Card -->
                    <section class="glass-panel table-card">
                        <div class="table-header" style="display: flex; justify-content: space-between; align-items: center;">
                            <div>
                                <h2>Rincian Transaksi Keuangan</h2>
                                <span style="font-size: 0.85rem; color: var(--text-secondary);">
                                    Menampilkan {{ $transactions->count() }} transaksi
                                </span>
                            </div>
                            @if (Auth::user()->hasPermission('delete_transactions'))
                                <button id="btnDeleteBulk" type="button" class="btn btn-danger" style="display: none; height: 38px; align-items: center; gap: 6px; padding: 0 16px; border-radius: 8px; font-size: 0.85rem; font-weight: 600;" onclick="submitBulkDelete()">
                                    <svg style="width: 16px; height: 16px;" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                    </svg>
                                    Hapus Terpilih (<span id="selectedTxCount">0</span>)
                                </button>
                            @endif
                        </div>

                        @if ($transactions->isEmpty())
                            <div class="empty-state">
                                <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" style="width: 48px; height: 48px;">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01" />
                                </svg>
                                <h3>Belum Ada Catatan Transaksi</h3>
                                <p style="color: var(--text-secondary); font-size: 0.9rem; margin-top: 4px;">
                                    Tambahkan transaksi uang masuk atau uang keluar untuk melihat catatan laporan keuangan di sini.
                                </p>
                            </div>
                        @else
                            <div class="table-wrapper">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            @if (Auth::user()->hasPermission('delete_transactions'))
                                                <th style="width: 40px; text-align: center;"><input type="checkbox" id="checkAllTx" style="cursor: pointer;" onclick="toggleCheckAllTx(this)"></th>
                                            @endif
                                            <th>No. Bukti</th>
                                            <th>Tanggal</th>
                                            <th>Jenis</th>
                                            <th>Kategori</th>
                                            <th>Akun Kas/Bank</th>
                                            <th>Keterangan</th>
                                            <th>Nominal</th>
                                            <th>Bukti Bon</th>
                                            <th>Petugas</th>
                                            <th>Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($transactions as $tx)
                                            <tr>
                                                @if (Auth::user()->hasPermission('delete_transactions'))
                                                    <td style="text-align: center;">
                                                        <input type="checkbox" class="tx-checkbox" value="{{ $tx->id }}" style="cursor: pointer;" onclick="updateBulkDeleteState()">
                                                    </td>
                                                @endif
                                                <td class="tx-number">
                                                    {{ $tx->transaction_number }}
                                                </td>
                                                <td>{{ $tx->transaction_date->format('d/m/Y') }}</td>
                                                <td>
                                                    @if ($tx->type === 'in')
                                                        <span class="badge badge-in">Masuk</span>
                                                        <div style="margin-top: 4px;">
                                                            @if ($tx->is_transferred)
                                                                <span class="badge" style="background: rgba(16, 185, 129, 0.15); color: #10b981; border: 1px solid rgba(16, 185, 129, 0.3); font-size: 0.65rem; padding: 1px 4px; display: inline-block;">Diterima</span>
                                                            @else
                                                                <span class="badge" style="background: rgba(245, 158, 11, 0.15); color: #f59e0b; border: 1px solid rgba(245, 158, 11, 0.3); font-size: 0.65rem; padding: 1px 4px; display: inline-block;">Pending</span>
                                                            @endif
                                                        </div>
                                                    @else
                                                        <span class="badge badge-out">Keluar</span>
                                                        @if ($tx->is_reimbursement)
                                                            <div style="margin-top: 4px;">
                                                                @if ($tx->reimbursement_status === 'pending')
                                                                    <span class="badge" style="background: rgba(245, 158, 11, 0.15); color: #f59e0b; border: 1px solid rgba(245, 158, 11, 0.3); font-size: 0.7rem; padding: 2px 6px; display: inline-block;">Reimburse (Pending)</span>
                                                                @else
                                                                    <span class="badge" style="background: rgba(16, 185, 129, 0.15); color: #10b981; border: 1px solid rgba(16, 185, 129, 0.3); font-size: 0.7rem; padding: 2px 6px; display: inline-block;">Reimburse (Ditransfer)</span>
                                                                @endif
                                                            </div>
                                                        @else
                                                            <div style="margin-top: 4px;">
                                                                @if ($tx->is_transferred)
                                                                    <span class="badge" style="background: rgba(16, 185, 129, 0.15); color: #10b981; border: 1px solid rgba(16, 185, 129, 0.3); font-size: 0.65rem; padding: 1px 4px; display: inline-block;">Ditransfer</span>
                                                                @else
                                                                    <span class="badge" style="background: rgba(245, 158, 11, 0.15); color: #f59e0b; border: 1px solid rgba(245, 158, 11, 0.3); font-size: 0.65rem; padding: 1px 4px; display: inline-block;">Pending Transfer</span>
                                                                @endif
                                                            </div>
                                                        @endif
                                                    @endif
                                                </td>
                                                <td>
                                                    <span style="font-weight: 500;">{{ $tx->category }}</span>
                                                </td>
                                                <td>
                                                    <span style="font-size: 0.85rem; color: var(--text-secondary);">{{ $tx->payment_source }}</span>
                                                </td>
                                                <td style="max-width: 250px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" title="{{ $tx->description }}">
                                                    {{ $tx->description ?? '-' }}
                                                </td>
                                                <td>
                                                    @if ($tx->type === 'in')
                                                        <span class="amount-in">+ Rp {{ number_format($tx->amount, 0, ',', '.') }}</span>
                                                    @else
                                                        <span class="amount-out">- Rp {{ number_format($tx->amount, 0, ',', '.') }}</span>
                                                    @endif
                                                </td>
                                                <td>
                                                    <div style="display: flex; flex-direction: column; gap: 4px; align-items: flex-start;">
                                                        @if ($tx->attachments->isNotEmpty())
                                                            <button 
                                                                type="button" 
                                                                class="btn btn-secondary btn-sm" 
                                                                style="padding: 4px 10px; font-size: 0.75rem; width: 100%; text-align: center;"
                                                                onclick="openReceiptModal('{{ $tx->attachments->first()->url }}', '{{ addslashes($tx->attachments->first()->original_name) }}')"
                                                            >
                                                                Lihat Bon
                                                            </button>
                                                        @endif
                                                        
                                                        @if ($tx->is_reimbursement && $tx->transfer_proof_path)
                                                            <button 
                                                                type="button" 
                                                                class="btn btn-info btn-sm" 
                                                                style="padding: 4px 10px; font-size: 0.75rem; width: 100%; text-align: center; background: rgba(59, 130, 246, 0.1); color: #3b82f6; border: 1px solid rgba(59, 130, 246, 0.2);"
                                                                onclick="openReceiptModal('{{ route('web.attachments.show', ['path' => $tx->transfer_proof_path]) }}', 'Bukti Transfer')"
                                                            >
                                                                Lihat Transfer
                                                            </button>
                                                        @endif
                                                        
                                                        @if ($tx->attachments->isEmpty() && (!$tx->is_reimbursement || !$tx->transfer_proof_path))
                                                            <span style="color: var(--text-muted); font-size: 0.8rem;">Tidak ada</span>
                                                        @endif
                                                    </div>
                                                </td>
                                                <td>
                                                    <span style="font-size: 0.85rem; color: var(--text-secondary);">{{ $tx->creator }}</span>
                                                </td>
                                                <td>
                                                    <div style="display: flex; gap: 4px;">
                                                        <!-- Reimbursement transfer button -->
                                                        @if ($tx->is_reimbursement && $tx->reimbursement_status === 'pending' && Auth::user()->hasPermission('edit_transactions'))
                                                            <button 
                                                                type="button" 
                                                                class="btn-action" 
                                                                style="background: rgba(16, 185, 129, 0.1); color: #10b981; border: 1px solid rgba(16, 185, 129, 0.2);"
                                                                title="Transfer Pembayaran"
                                                                onclick="openReimburseTransferModal({{ $tx->id }}, '{{ $tx->transaction_number }}', {{ $tx->amount }})"
                                                            >
                                                                <svg style="width: 16px; height: 16px;" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z" />
                                                                </svg>
                                                            </button>
                                                        @endif

                                                        <!-- Edit button -->
                                                        @if (Auth::user()->hasPermission('edit_transactions'))
                                                            <button 
                                                            type="button" 
                                                            class="btn-action btn-action-edit" 
                                                            title="Ubah"
                                                            data-tx="{{ json_encode([
                                                                'id' => $tx->id,
                                                                'type' => $tx->type,
                                                                'date' => $tx->transaction_date->format('Y-m-d'),
                                                                'amount' => $tx->amount,
                                                                'categoryId' => $tx->category_id,
                                                                'paymentAccountId' => $tx->payment_account_id,
                                                                'description' => $tx->description,
                                                                'hasReceipt' => $tx->attachments->isNotEmpty(),
                                                                'receiptUrl' => $tx->attachments->isNotEmpty() ? $tx->attachments->first()->url : '',
                                                                'receiptName' => $tx->attachments->isNotEmpty() ? $tx->attachments->first()->original_name : '',
                                                                'isReimbursement' => $tx->is_reimbursement,
                                                                'reimbursementStatus' => $tx->reimbursement_status,
                                                                'hasTransferProof' => !empty($tx->transfer_proof_path),
                                                                'transferProofUrl' => $tx->transfer_proof_path ? route('web.attachments.show', ['path' => $tx->transfer_proof_path]) : '',
                                                                'transferProofName' => $tx->transfer_proof_path ? basename($tx->transfer_proof_path) : '',
                                                                'isTransferred' => $tx->is_transferred
                                                            ]) }}"
                                                            onclick="initiateEdit(this)"
                                                        >
                                                            <svg style="width: 16px; height: 16px;" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                                            </svg>
                                                        </button>
                                                        @endif

                                                        <!-- Delete button -->
                                                        @if (Auth::user()->hasPermission('delete_transactions'))
                                                            <button 
                                                                type="button" 
                                                                class="btn-action btn-action-delete" 
                                                                title="Hapus"
                                                                onclick="confirmDeleteTransaction({{ $tx->id }}, '{{ $tx->transaction_number }}')"
                                                            >
                                                                <svg style="width: 16px; height: 16px;" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                                </svg>
                                                            </button>
                                                        @endif
                                                    </div>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    </section>
                </div>

                <!-- Section: Settings (COA) -->
                @if (Auth::user()->role === 'owner')
                    <div id="section-settings" class="tab-section" style="display: none;">
                        <section class="glass-panel table-card">
                            <div class="table-header">
                                <h2>Chart of Accounts (Daftar Akun)</h2>
                                <span style="font-size: 0.85rem; color: var(--text-secondary);">
                                    Menampilkan {{ $allAccounts->count() }} akun aktif
                                </span>
                            </div>
                            <div class="table-wrapper">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>Kode Akun</th>
                                            <th>Nama Akun</th>
                                            <th>Tipe</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($allAccounts as $acc)
                                            <tr>
                                                <td style="font-family: monospace; font-weight: 600; color: var(--text-secondary);">{{ $acc->code }}</td>
                                                <td><span style="font-weight: 500;">{{ $acc->name }}</span></td>
                                                <td>
                                                    @if (Str::startsWith($acc->code, '11'))
                                                        <span class="badge" style="background: rgba(99, 102, 241, 0.15); color: #a5b4fc; border: 1px solid rgba(99, 102, 241, 0.3);">Asset / Kas</span>
                                                    @elseif (Str::startsWith($acc->code, '41'))
                                                        <span class="badge" style="background: rgba(16, 185, 129, 0.15); color: #a7f3d0; border: 1px solid rgba(16, 185, 129, 0.3);">Pendapatan</span>
                                                    @elseif (Str::startsWith($acc->code, '51'))
                                                        <span class="badge" style="background: rgba(239, 68, 68, 0.15); color: #fca5a5; border: 1px solid rgba(239, 68, 68, 0.3);">Beban</span>
                                                    @else
                                                        <span class="badge badge-secondary">Lainnya</span>
                                                    @endif
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </section>
                    </div> <!-- Close section-settings -->
                @endif

                <!-- Sub-panel: Settlements (Advance & Settlement) -->
                <div id="sub-section-settlements" class="sub-tab-panel" style="display: none;">
                    <section class="kpi-grid">
                        <div class="kpi-card glass-panel kpi-outflow">
                            <div class="kpi-title">Outstanding (Hutang Karyawan)</div>
                            <div class="kpi-value amount-out">Rp {{ isset($settlementSummary) ? number_format($settlementSummary->total_outstanding, 0, ',', '.') : 0 }}</div>
                            <div class="kpi-desc">Total dana kas yang belum diselesaikan (dilaporkan bon)</div>
                        </div>
                        <div class="kpi-card glass-panel kpi-inflow">
                            <div class="kpi-title">Selesai (Settled)</div>
                            <div class="kpi-value amount-in">Rp {{ isset($settlementSummary) ? number_format($settlementSummary->total_settled, 0, ',', '.') : 0 }}</div>
                            <div class="kpi-desc">Total dana advance yang telah di-settle dengan bon fisik</div>
                        </div>
                    </section>

                    <div class="action-filter-bar" style="margin-top: 24px; display: flex; justify-content: space-between; align-items: center;">
                        <div>
                            @if (Auth::user()->hasPermission('delete_settlements'))
                                <button id="btnDeleteBulkSettlements" type="button" class="btn btn-danger" style="display: none; height: 38px; align-items: center; gap: 6px; padding: 0 16px; border-radius: 8px; font-size: 0.85rem; font-weight: 600;" onclick="submitBulkDeleteSettlements()">
                                    <svg style="width: 16px; height: 16px;" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                    </svg>
                                    Hapus Terpilih (<span id="selectedSettlementCount">0</span>)
                                </button>
                            @endif
                        </div>
                        @if (Auth::user()->hasPermission('create_settlements'))
                            <button onclick="openAdvanceModal()" class="btn btn-primary" style="display: inline-flex; align-items: center; gap: 8px;">
                                <span style="font-size: 1.1rem; line-height: 1;">+</span> Buat Advance
                            </button>
                        @endif
                    </div>

                    <section class="glass-panel table-card" style="margin-top: 16px;">
                        <div class="table-header">
                            <h2>Daftar Advance (Advance Payments)</h2>
                        </div>
                        <div class="table-wrapper">
                            <table class="table">
                                <thead>
                                    <tr>
                                        @if (Auth::user()->hasPermission('delete_settlements'))
                                            <th style="width: 40px; text-align: center;"><input type="checkbox" id="checkAllSettlements" style="cursor: pointer;" onclick="toggleCheckAllSettlements(this)"></th>
                                        @endif
                                        <th>No. Bukti</th>
                                        <th>Tanggal</th>
                                        <th>Penerima</th>
                                        <th>Penginput</th>
                                        <th>Nominal Advance</th>
                                        <th>Sumber Kas</th>
                                        <th>Keterangan</th>
                                        <th>Nominal Bon</th>
                                        <th>Status</th>
                                        <th>Bon Fisik</th>
                                        <th>Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @if(isset($advances) && $advances->isNotEmpty())
                                        @foreach ($advances as $adv)
                                            <tr>
                                                @if (Auth::user()->hasPermission('delete_settlements'))
                                                    <td style="text-align: center;">
                                                        <input type="checkbox" class="settlement-checkbox" value="{{ $adv->id }}" style="cursor: pointer;" onclick="updateBulkDeleteSettlementsState()">
                                                    </td>
                                                @endif
                                                <td class="tx-number">{{ $adv->transaction_number }}</td>
                                                <td>{{ $adv->transaction_date->format('d/m/Y') }}</td>
                                                <td><span style="font-size: 0.85rem; font-weight: 600; color: var(--text-secondary);">{{ $adv->recipient_name ?? '-' }}</span></td>
                                                <td><span style="font-size: 0.85rem; color: var(--text-secondary);">{{ $adv->creator }}</span></td>
                                                <td><span class="amount-out">Rp {{ number_format($adv->amount, 0, ',', '.') }}</span></td>
                                                <td><span style="font-size: 0.85rem; color: var(--text-secondary);">{{ $adv->payment_source }}</span></td>
                                                <td style="max-width: 200px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" title="{{ $adv->description }}">{{ $adv->description }}</td>
                                                <td>
                                                    @if ($adv->advance_status === 'settled')
                                                        <span class="amount-in">Rp {{ number_format($adv->settlement_amount, 0, ',', '.') }}</span>
                                                    @else
                                                        <span style="color: var(--text-muted);">-</span>
                                                    @endif
                                                </td>
                                                <td>
                                                    @if ($adv->advance_status === 'open')
                                                        <span class="badge" style="background: rgba(239, 68, 68, 0.15); color: #fca5a5; border: 1px solid rgba(239, 68, 68, 0.3);">Belum Dilaporkan</span>
                                                    @else
                                                        <span class="badge badge-in">Selesai (Settled)</span>
                                                    @endif
                                                    <div style="margin-top: 4px;">
                                                        @if ($adv->is_transferred)
                                                            <span class="badge" style="background: rgba(16, 185, 129, 0.15); color: #10b981; border: 1px solid rgba(16, 185, 129, 0.3); font-size: 0.65rem; padding: 1px 4px; display: inline-block;">Sudah Ditransfer</span>
                                                        @else
                                                            <span class="badge" style="background: rgba(245, 158, 11, 0.15); color: #f59e0b; border: 1px solid rgba(245, 158, 11, 0.3); font-size: 0.65rem; padding: 1px 4px; display: inline-block;">Belum Ditransfer</span>
                                                        @endif
                                                    </div>
                                                </td>
                                                <td>
                                                    @if ($adv->advance_status === 'settled' && $adv->attachment)
                                                        <button type="button" class="btn btn-secondary btn-sm" style="padding: 4px 10px; font-size: 0.75rem;" onclick="openReceiptModal('{{ $adv->attachment->url }}', '{{ addslashes($adv->attachment->original_name) }}')">
                                                            Lihat Bon
                                                        </button>
                                                    @else
                                                        <span style="color: var(--text-muted); font-size: 0.8rem;">Tidak ada</span>
                                                    @endif
                                                </td>
                                                <td>
                                                    <div style="display: flex; gap: 4px; align-items: center;">
                                                        @if ($adv->advance_status === 'open')
                                                            @if (Auth::user()->hasPermission('process_settlements'))
                                                                <button type="button" class="btn btn-success btn-sm" style="font-weight: 600; padding: 6px 12px; border-radius: 6px;" onclick="openSettleModal({{ $adv->id }}, '{{ $adv->transaction_number }}', {{ $adv->amount }}, '{{ addslashes($adv->recipient_name) }}')">
                                                                    Laporkan Bon
                                                                </button>
                                                            @endif
                                                        @endif

                                                        @if (Auth::user()->hasPermission('edit_settlements'))
                                                            <button 
                                                                type="button" 
                                                                class="btn-action btn-action-edit" 
                                                                title="Ubah Advance / Settlement"
                                                                onclick="initiateEditSettlement(this)"
                                                                data-adv="{{ json_encode([
                                                                    'id' => $adv->id,
                                                                    'transaction_number' => $adv->transaction_number,
                                                                    'transaction_date' => $adv->transaction_date->format('Y-m-d'),
                                                                    'recipient_name' => $adv->recipient_name,
                                                                    'amount' => number_format($adv->amount, 0, ',', '.'),
                                                                    'payment_account_id' => $adv->payment_account_id,
                                                                    'description' => $adv->description,
                                                                    'advance_status' => $adv->advance_status,
                                                                    'expense_account_id' => $adv->expense_account_id ?? '',
                                                                    'settlement_amount' => $adv->settlement_amount ? number_format($adv->settlement_amount, 0, ',', '.') : '',
                                                                ]) }}"
                                                            >
                                                                <svg style="width: 16px; height: 16px;" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
                                                                </svg>
                                                            </button>
                                                        @endif

                                                        @if (Auth::user()->hasPermission('delete_settlements'))
                                                            <button 
                                                                type="button" 
                                                                class="btn-action btn-action-delete" 
                                                                title="Hapus"
                                                                onclick="confirmDeleteSettlement({{ $adv->id }}, '{{ $adv->transaction_number }}')"
                                                            >
                                                                <svg style="width: 16px; height: 16px;" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                                </svg>
                                                            </button>
                                                        @endif

                                                        @if (
                                                            ($adv->advance_status === 'open' && !Auth::user()->hasPermission('process_settlements') && !Auth::user()->hasPermission('edit_settlements') && !Auth::user()->hasPermission('delete_settlements')) ||
                                                            ($adv->advance_status === 'settled' && !Auth::user()->hasPermission('edit_settlements') && !Auth::user()->hasPermission('delete_settlements'))
                                                        )
                                                            <span style="color: var(--text-muted); font-size: 0.8rem;">No Izin</span>
                                                        @endif
                                                    </div>
                                                </td>
                                            </tr>
                                        @endforeach
                                    @else
                                        <tr>
                                            <td colspan="12" style="text-align: center; color: var(--text-muted); padding: 32px 0;">Tidak ada catatan transaksi advance.</td>
                                        </tr>
                                    @endif
                                </tbody>
                            </table>
                        </div>
                    </section>
                    </div> <!-- Close sub-section-settlements -->

                <!-- Sub-panel: Cash Advances (Pinjaman Karyawan) -->
                <div id="sub-section-cash-advances" class="sub-tab-panel" style="display: none;">
                    <section class="kpi-grid">
                        <div class="kpi-card glass-panel kpi-outflow">
                            <div class="kpi-title">Outstanding Pinjaman (Piutang Karyawan)</div>
                            <div class="kpi-value amount-out">Rp {{ number_format($loanSummary->total_outstanding, 0, ',', '.') }}</div>
                            <div class="kpi-desc">Sisa piutang pinjaman aktif yang belum lunas</div>
                        </div>
                        <div class="kpi-card glass-panel kpi-inflow">
                            <div class="kpi-title">Pinjaman Lunas</div>
                            <div class="kpi-value amount-in">Rp {{ number_format($loanSummary->total_repaid, 0, ',', '.') }}</div>
                            <div class="kpi-desc">Akumulasi total pinjaman yang sudah dikembalikan penuh</div>
                        </div>
                    </section>

                    <div class="action-filter-bar" style="justify-content: flex-end; margin-top: 16px;">
                        @if (Auth::user()->hasPermission('create_cash_advances'))
                            <button onclick="openLoanModal()" class="btn btn-primary" style="display: inline-flex; align-items: center; gap: 8px;">
                                <span style="font-size: 1.1rem; line-height: 1;">+</span> Buat Pinjaman Baru
                            </button>
                        @endif
                    </div>

                    <section class="glass-panel table-card" style="margin-top: 16px;">
                        <div class="table-header">
                            <h2>Daftar Cash Advance (Pinjaman Karyawan)</h2>
                        </div>
                        <div class="table-wrapper">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th style="width: 40px;"></th>
                                        <th>No. Bukti</th>
                                        <th>Tanggal</th>
                                        <th>Nama Karyawan</th>
                                        <th>Penginput</th>
                                        <th>Nominal Pinjaman</th>
                                        <th>Total Dibayar</th>
                                        <th>Sisa Pinjaman</th>
                                        <th>Sumber Dana</th>
                                        <th>Keterangan</th>
                                        <th>Status</th>
                                        <th>Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @if ($loans->isNotEmpty())
                                        @foreach ($loans as $loan)
                                            <tr>
                                                <td style="text-align: center;">
                                                    @if (count($loan->repayments) > 0)
                                                        <button onclick="toggleRepaymentsRow({{ $loan->id }}, this)" class="btn-toggle-subtable" style="background: none; border: none; color: var(--text-secondary); cursor: pointer; outline: none; padding: 4px;">
                                                            <svg style="width: 16px; height: 16px; transition: transform 0.2s;" fill="none" viewBox="0 0 24 24" stroke="currentColor" id="toggle-icon-{{ $loan->id }}">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7" />
                                                            </svg>
                                                        </button>
                                                    @endif
                                                </td>
                                                <td class="tx-number">{{ $loan->transaction_number }}</td>
                                                <td>{{ $loan->transaction_date->format('d/m/Y') }}</td>
                                                <td style="font-weight: 600; color: var(--text-primary);">{{ $loan->recipient_name }}</td>
                                                <td>{{ $loan->creator }}</td>
                                                <td style="font-weight: 600;">Rp {{ number_format($loan->amount, 0, ',', '.') }}</td>
                                                <td style="color: #34d399;">Rp {{ number_format($loan->loan_repaid_amount, 0, ',', '.') }}</td>
                                                <td style="font-weight: 600; color: {{ $loan->remaining_amount > 0 ? '#f87171' : 'var(--text-secondary)' }};">
                                                    Rp {{ number_format($loan->remaining_amount, 0, ',', '.') }}
                                                </td>
                                                <td>{{ $loan->payment_source }}</td>
                                                <td>{{ $loan->description }}</td>
                                                <td>
                                                    @if ($loan->loan_status === 'repaid')
                                                        <span class="badge badge-success">Lunas</span>
                                                    @else
                                                        <span class="badge badge-warning">Belum Lunas</span>
                                                    @endif
                                                    <div style="margin-top: 4px;">
                                                        @if ($loan->is_transferred)
                                                            <span class="badge" style="background: rgba(16, 185, 129, 0.15); color: #10b981; border: 1px solid rgba(16, 185, 129, 0.3); font-size: 0.65rem; padding: 1px 4px; display: inline-block;">Sudah Ditransfer</span>
                                                        @else
                                                            <span class="badge" style="background: rgba(245, 158, 11, 0.15); color: #f59e0b; border: 1px solid rgba(245, 158, 11, 0.3); font-size: 0.65rem; padding: 1px 4px; display: inline-block;">Belum Ditransfer</span>
                                                        @endif
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="action-buttons-group">
                                                        @if ($loan->loan_status !== 'repaid' && Auth::user()->hasPermission('create_cash_advances'))
                                                            <button 
                                                                type="button" 
                                                                class="btn-action btn-action-edit" 
                                                                title="Bayar Angsuran"
                                                                onclick="openRepaymentModal({{ $loan->id }}, '{{ $loan->transaction_number }}', '{{ $loan->recipient_name }}', '{{ number_format($loan->remaining_amount, 0, ',', '.') }}')"
                                                                style="color: #60a5fa;"
                                                            >
                                                                <svg style="width: 16px; height: 16px;" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                                </svg>
                                                            </button>
                                                        @endif

                                                        @if (Auth::user()->hasPermission('edit_cash_advances'))
                                                            <button 
                                                                type="button" 
                                                                class="btn-action btn-action-edit" 
                                                                title="Ubah Pinjaman"
                                                                onclick="initiateEditLoan(this)"
                                                                data-loan="{{ json_encode([
                                                                    'id' => $loan->id,
                                                                    'transaction_number' => $loan->transaction_number,
                                                                    'transaction_date' => $loan->transaction_date->format('Y-m-d'),
                                                                    'recipient_name' => $loan->recipient_name,
                                                                    'amount' => number_format($loan->amount, 0, ',', '.'),
                                                                    'payment_account_id' => $loan->payment_account_id,
                                                                    'description' => $loan->description,
                                                                    'is_transferred' => $loan->is_transferred,
                                                                    'transferred_amount' => $loan->transferred_amount ? number_format($loan->transferred_amount, 0, ',', '.') : '',
                                                                ]) }}"
                                                            >
                                                                <svg style="width: 16px; height: 16px;" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
                                                                </svg>
                                                            </button>
                                                        @endif

                                                        @if (Auth::user()->hasPermission('delete_cash_advances'))
                                                            <button 
                                                                type="button" 
                                                                class="btn-action btn-action-delete" 
                                                                title="Hapus Pinjaman"
                                                                onclick="confirmDeleteLoan({{ $loan->id }}, '{{ $loan->transaction_number }}')"
                                                            >
                                                                <svg style="width: 16px; height: 16px;" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                                </svg>
                                                            </button>
                                                        @endif
                                                    </div>
                                                </td>
                                            </tr>

                                            @if (count($loan->repayments) > 0)
                                                <tr id="repayments-row-{{ $loan->id }}" class="repayments-subtable-row" style="display: none; background: rgba(255, 255, 255, 0.02);">
                                                    <td></td>
                                                    <td colspan="11" style="padding: 12px 24px;">
                                                        <div style="border-left: 3px solid var(--accent-color); padding-left: 16px; margin: 8px 0;">
                                                            <h4 style="margin: 0 0 8px 0; font-size: 0.9rem; color: var(--text-secondary);">Riwayat Angsuran Pelunasan</h4>
                                                            <table class="table" style="font-size: 0.85rem; width: 100%;">
                                                                <thead>
                                                                    <tr style="background: rgba(255,255,255,0.03);">
                                                                        <th>No. Angsuran</th>
                                                                        <th>Tanggal</th>
                                                                        <th>Nominal Angsuran</th>
                                                                        <th>Diterima Di</th>
                                                                        <th>Keterangan</th>
                                                                        <th>Pencatat</th>
                                                                        <th style="width: 80px; text-align: center;">Aksi</th>
                                                                    </tr>
                                                                </thead>
                                                                <tbody>
                                                                    @foreach ($loan->repayments as $rep)
                                                                        <tr>
                                                                            <td class="tx-number">{{ $rep->transaction_number }}</td>
                                                                            <td>{{ $rep->transaction_date->format('d/m/Y') }}</td>
                                                                            <td style="font-weight: 600; color: #34d399;">Rp {{ number_format($rep->amount, 0, ',', '.') }}</td>
                                                                            <td>{{ $rep->destination_account }}</td>
                                                                            <td>{{ $rep->description }}</td>
                                                                            <td>{{ $rep->creator }}</td>
                                                                            <td style="text-align: center;">
                                                                                @if (Auth::user()->hasPermission('delete_cash_advances'))
                                                                                    <button 
                                                                                        type="button" 
                                                                                        class="btn-action btn-action-delete" 
                                                                                        title="Hapus Angsuran"
                                                                                        onclick="confirmDeleteRepayment({{ $rep->id }}, '{{ $rep->transaction_number }}')"
                                                                                        style="padding: 2px 6px;"
                                                                                    >
                                                                                        <svg style="width: 14px; height: 14px;" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                                                        </svg>
                                                                                    </button>
                                                                                @else
                                                                                    <span style="color: var(--text-muted); font-size: 0.75rem;">No Izin</span>
                                                                                @endif
                                                                            </td>
                                                                        </tr>
                                                                    @endforeach
                                                                </tbody>
                                                            </table>
                                                        </div>
                                                    </td>
                                                </tr>
                                            @endif
                                        @endforeach
                                    @else
                                        <tr>
                                            <td colspan="12" style="text-align: center; color: var(--text-muted); padding: 32px 0;">Tidak ada catatan transaksi cash advance.</td>
                                        </tr>
                                    @endif
                                </tbody>
                            </table>
                        </div>
                    </section>
                </div> <!-- Close sub-section-cash-advances -->
                </div> <!-- Close section-transactions -->

                <!-- Section: Buku Besar (General Ledger) -->
                <div id="section-ledger" class="tab-section" style="display: none;">
                    <div class="action-filter-bar no-print">
                        <form action="{{ route('dashboard') }}" method="GET" class="filter-form" style="display: flex; flex-wrap: wrap; gap: 16px; align-items: flex-end; width: 100%;">
                            <input type="hidden" name="activeTab" value="ledger">
                            
                            <div class="form-group" style="min-width: 250px;">
                                <label for="ledger_account_id" class="form-label" style="font-size: 0.75rem; margin-bottom: 4px;">Pilih Akun (COA)</label>
                                <select name="ledger_account_id" id="ledger_account_id" class="form-input" style="padding: 8px 12px; font-size: 0.85rem;" required>
                                    <option value="">-- Pilih Akun --</option>
                                    @foreach ($allAccounts->sortBy('code') as $acc)
                                        <option value="{{ $acc->id }}" {{ $ledger_account_id == $acc->id ? 'selected' : '' }}>
                                            {{ $acc->code }} - {{ $acc->name }} ({{ ucfirst($acc->type) }})
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label for="ledger_start_date" class="form-label" style="font-size: 0.75rem; margin-bottom: 4px;">Tanggal Mulai</label>
                                <input 
                                    type="date" 
                                    name="ledger_start_date" 
                                    id="ledger_start_date" 
                                    class="form-input" 
                                    value="{{ $ledger_start_date }}"
                                    style="padding: 8px 12px; font-size: 0.85rem;"
                                >
                            </div>
                            
                            <div class="form-group">
                                <label for="ledger_end_date" class="form-label" style="font-size: 0.75rem; margin-bottom: 4px;">Tanggal Akhir</label>
                                <input 
                                    type="date" 
                                    name="ledger_end_date" 
                                    id="ledger_end_date" 
                                    class="form-input" 
                                    value="{{ $ledger_end_date }}"
                                    style="padding: 8px 12px; font-size: 0.85rem;"
                                >
                            </div>
                            
                            <button type="submit" class="btn btn-primary btn-sm" style="height: 38px;">
                                Filter Laporan
                            </button>
                            
                            @if ($ledger_account_id)
                                <a href="{{ route('dashboard', ['activeTab' => 'ledger']) }}" class="btn btn-secondary btn-sm" style="height: 38px;">
                                    Reset
                                </a>
                                <button type="button" onclick="window.print()" class="btn btn-secondary btn-sm" style="height: 38px; display: inline-flex; align-items: center; gap: 6px;">
                                    <svg style="width: 16px; height: 16px;" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                                    </svg>
                                    Cetak Buku Besar (PDF)
                                </button>
                            @endif
                        </form>
                    </div>

                    @if (!$ledgerAccount)
                        <!-- Empty State: No Account Selected -->
                        <div class="glass-panel" style="padding: 48px; text-align: center; margin-top: 16px;">
                            <svg style="width: 48px; height: 48px; color: var(--text-muted); margin: 0 auto 16px;" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
                            </svg>
                            <h3 style="font-size: 1.15rem; font-weight: 600; margin-bottom: 8px;">Pilih Akun Terlebih Dahulu</h3>
                            <p style="color: var(--text-secondary); font-size: 0.9rem; max-width: 500px; margin: 0 auto;">
                                Silakan pilih salah satu Kode Akun (COA) dan tentukan rentang tanggal di atas untuk memuat laporan mutasi Buku Besar secara detail.
                            </p>
                        </div>
                    @else
                        <!-- Ledger Report Container -->
                        <div class="glass-panel table-card" style="margin-top: 16px; padding: 24px;">
                            <!-- Header Laporan (Hanya Muncul saat Cetak / Detail) -->
                            <div class="ledger-print-header" style="margin-bottom: 24px; border-bottom: 2px solid var(--border-glass); padding-bottom: 16px;">
                                <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                                    <div>
                                        <h2 style="font-size: 1.4rem; font-weight: 700; color: var(--text-primary);">LAPORAN BUKU BESAR</h2>
                                        <div style="font-size: 0.9rem; color: var(--text-secondary); margin-top: 4px;">
                                            Akun: <strong style="color: var(--text-primary);">{{ $ledgerAccount->code }} - {{ $ledgerAccount->name }}</strong>
                                        </div>
                                        <div style="font-size: 0.85rem; color: var(--text-muted); margin-top: 2px;">
                                            Tipe Akun: {{ ucfirst($ledgerAccount->type) }}
                                        </div>
                                    </div>
                                    <div style="text-align: right;">
                                        <div style="font-size: 0.9rem; font-weight: 600; color: var(--text-primary);">Finance System</div>
                                        <div style="font-size: 0.85rem; color: var(--text-secondary); margin-top: 4px;">
                                            Periode: {{ \Carbon\Carbon::parse($ledger_start_date)->format('d/m/Y') }} - {{ \Carbon\Carbon::parse($ledger_end_date)->format('d/m/Y') }}
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="table-wrapper">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>Tanggal</th>
                                            <th>No. Bukti</th>
                                            <th>Keterangan</th>
                                            <th>Petugas</th>
                                            <th style="text-align: right;">Debit</th>
                                            <th style="text-align: right;">Kredit</th>
                                            <th style="text-align: right;">Saldo Akhir</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <!-- Row 1: Saldo Awal -->
                                        <tr style="background: rgba(255, 255, 255, 0.02); font-weight: 600;">
                                            <td>{{ \Carbon\Carbon::parse($ledger_start_date)->format('d/m/Y') }}</td>
                                            <td style="color: var(--text-muted); font-style: italic;">-</td>
                                            <td><strong>SALDO AWAL (Opening Balance)</strong></td>
                                            <td style="color: var(--text-muted); font-style: italic;">-</td>
                                            <td style="text-align: right; color: var(--text-muted);">-</td>
                                            <td style="text-align: right; color: var(--text-muted);">-</td>
                                            <td style="text-align: right; color: var(--color-primary);">
                                                Rp {{ number_format($ledgerStartingBalance, 0, ',', '.') }}
                                            </td>
                                        </tr>

                                        <!-- Mutations -->
                                        @if ($ledgerEntries->isEmpty())
                                            <tr>
                                                <td colspan="7" style="text-align: center; color: var(--text-muted); padding: 24px 0; font-style: italic;">
                                                    Tidak ada mutasi transaksi untuk akun ini dalam periode terpilih.
                                                </td>
                                            </tr>
                                        @else
                                            @foreach ($ledgerEntries as $entry)
                                                <tr>
                                                    <td>{{ $entry->transaction_date->format('d/m/Y') }}</td>
                                                    <td class="tx-number">{{ $entry->transaction_number }}</td>
                                                    <td>{{ $entry->description }}</td>
                                                    <td>{{ $entry->creator }}</td>
                                                    <td style="text-align: right; font-weight: 500; color: {{ $entry->debit > 0 ? 'var(--color-success)' : 'inherit' }}">
                                                        {{ $entry->debit > 0 ? 'Rp ' . number_format($entry->debit, 0, ',', '.') : '-' }}
                                                    </td>
                                                    <td style="text-align: right; font-weight: 500; color: {{ $entry->credit > 0 ? 'var(--color-danger)' : 'inherit' }}">
                                                        {{ $entry->credit > 0 ? 'Rp ' . number_format($entry->credit, 0, ',', '.') : '-' }}
                                                    </td>
                                                    <td style="text-align: right; font-weight: 600;">
                                                        Rp {{ number_format($entry->running_balance, 0, ',', '.') }}
                                                    </td>
                                                </tr>
                                            @endforeach
                                        @endif

                                        <!-- Row Akhir: Saldo Akhir -->
                                        <tr style="background: rgba(255, 255, 255, 0.04); font-weight: 700; border-top: 2px solid var(--border-glass);">
                                            <td>{{ \Carbon\Carbon::parse($ledger_end_date)->format('d/m/Y') }}</td>
                                            <td style="color: var(--text-muted); font-style: italic;">-</td>
                                            <td><strong>SALDO AKHIR (Closing Balance)</strong></td>
                                            <td style="color: var(--text-muted); font-style: italic;">-</td>
                                            <td style="text-align: right; color: var(--color-success);">
                                                {{ $ledgerEntries->sum('debit') > 0 ? 'Rp ' . number_format($ledgerEntries->sum('debit'), 0, ',', '.') : '-' }}
                                            </td>
                                            <td style="text-align: right; color: var(--color-danger);">
                                                {{ $ledgerEntries->sum('credit') > 0 ? 'Rp ' . number_format($ledgerEntries->sum('credit'), 0, ',', '.') : '-' }}
                                            </td>
                                            <td style="text-align: right; color: var(--color-primary); font-size: 1.05rem;">
                                                Rp {{ number_format($ledgerEndingBalance, 0, ',', '.') }}
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    @endif
                </div>

                <!-- Section: Users & Roles (Management) -->
                @if (Auth::user()->role === 'owner')
                    <div id="section-users" class="tab-section" style="display: none;">
                        <div class="action-filter-bar" style="justify-content: flex-end;">
                            <button onclick="openUserModal()" class="btn btn-primary" style="display: inline-flex; align-items: center; gap: 8px;">
                                <span style="font-size: 1.1rem; line-height: 1;">+</span> Tambah User Baru
                            </button>
                        </div>

                        <section class="glass-panel table-card" style="margin-top: 16px;">
                            <div class="table-header">
                                <h2>Manajemen Pengguna & Hak Akses</h2>
                            </div>
                            <div class="table-wrapper">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>Nama</th>
                                            <th>Email</th>
                                            <th>Peran</th>
                                            <th>Hak Akses (Permissions)</th>
                                            <th>Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @if(isset($users) && $users->isNotEmpty())
                                            @foreach ($users as $usr)
                                                <tr>
                                                    <td><span style="font-weight: 500;">{{ $usr->name }}</span></td>
                                                    <td><span style="color: var(--text-secondary);">{{ $usr->email }}</span></td>
                                                    <td>
                                                        <span class="role-badge role-badge-{{ $usr->role }}">
                                                            {{ $usr->role === 'owner' ? 'Owner' : 'Staff Finance' }}
                                                        </span>
                                                    </td>
                                                    <td style="max-width: 350px; white-space: normal; line-height: 1.6;">
                                                        @if ($usr->role === 'owner')
                                                            <span class="badge" style="background: rgba(16, 185, 129, 0.15); color: #a7f3d0; border: 1px solid rgba(16, 185, 129, 0.3); margin-bottom: 4px;">Akses Penuh (Owner Bypass)</span>
                                                        @elseif (is_array($usr->permissions) && count($usr->permissions) > 0)
                                                            @php
                                                                $permLabels = [
                                                                    'view_transactions' => 'Lihat Transaksi',
                                                                    'create_transactions' => 'Tambah Transaksi',
                                                                    'edit_transactions' => 'Ubah Transaksi',
                                                                    'delete_transactions' => 'Hapus Transaksi',
                                                                    'view_settlements' => 'Lihat Settlement',
                                                                    'create_settlements' => 'Tambah Settlement',
                                                                    'process_settlements' => 'Proses Settle',
                                                                    'edit_settlements' => 'Ubah Settlement',
                                                                    'delete_settlements' => 'Hapus Settlement',
                                                                    'view_cash_advances' => 'Lihat Cash Advance',
                                                                    'create_cash_advances' => 'Tambah Cash Advance',
                                                                    'edit_cash_advances' => 'Ubah Cash Advance',
                                                                    'delete_cash_advances' => 'Hapus Cash Advance',
                                                                    'view_coa' => 'Lihat COA'
                                                                ];
                                                            @endphp
                                                            @foreach ($usr->permissions as $p)
                                                                <span class="badge badge-secondary" style="margin-right: 4px; margin-bottom: 4px; display: inline-block;">{{ $permLabels[$p] ?? $p }}</span>
                                                            @endforeach
                                                        @else
                                                            <span style="color: var(--text-muted); font-size: 0.85rem;">Tidak ada izin aktif</span>
                                                        @endif
                                                    </td>
                                                    <td>
                                                        <button type="button" class="btn-action btn-action-edit" title="Edit" 
                                                                data-user="{{ json_encode([
                                                                    'id' => $usr->id,
                                                                    'name' => $usr->name,
                                                                    'email' => $usr->email,
                                                                    'role' => $usr->role,
                                                                    'permissions' => $usr->permissions ?? []
                                                                ]) }}"
                                                                onclick="editUser(this)">
                                                            <svg style="width: 16px; height: 16px;" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                                            </svg>
                                                        </button>
                                                    </td>
                                                </tr>
                                            @endforeach
                                        @else
                                            <tr>
                                                <td colspan="5" style="text-align: center; color: var(--text-muted); padding: 32px 0;">Tidak ada pengguna staff lain.</td>
                                            </tr>
                                        @endif
                                    </tbody>
                                </table>
                            </div>
                        </section>
                    </div>
                @endif
            </main>
        </div>
    </div>

    <!-- Hidden Delete Form -->
    <form id="deleteTransactionForm" action="" method="POST" style="display: none;">
        @csrf
        @method('DELETE')
    </form>

    <!-- Hidden Bulk Delete Form -->
    <form id="bulkDeleteForm" action="{{ route('web.transactions.bulkDestroy') }}" method="POST" style="display: none;">
        @csrf
        @method('DELETE')
        <div id="bulkDeleteIdsContainer"></div>
    </form>

    <!-- Hidden Delete Settlement Form -->
    <form id="deleteSettlementForm" action="" method="POST" style="display: none;">
        @csrf
        @method('DELETE')
    </form>

    <!-- Hidden Bulk Delete Settlements Form -->
    <form id="bulkDeleteSettlementsForm" action="{{ route('web.settlements.bulkDestroy') }}" method="POST" style="display: none;">
        @csrf
        @method('DELETE')
        <div id="bulkDeleteSettlementsIdsContainer"></div>
    </form>

    <!-- Universal Transaction Modal -->
    <div id="transactionModal" class="modal-overlay">
        <div class="modal-card glass-panel">
            <div class="modal-header">
                <h3 id="modalTitle">Tambah Transaksi</h3>
                <button onclick="closeTransactionModal()" class="modal-close">&times;</button>
            </div>
            <form id="transactionForm" action="{{ route('web.transactions.store') }}" method="POST" enctype="multipart/form-data">
                @csrf
                <input type="hidden" name="_method" id="formMethod" value="POST">
                <input type="hidden" name="type" id="txType" value="out">
                
                <div class="modal-body">
                    <!-- Tanggal Transaksi -->
                    <div class="form-group">
                        <label for="tx_date" class="form-label">Tanggal Transaksi</label>
                        <input type="date" name="transaction_date" id="tx_date" class="form-input" value="{{ date('Y-m-d') }}" required>
                    </div>

                    <!-- Nominal / Amount -->
                    <div class="form-group">
                        <label for="tx_amount" class="form-label">Nominal (Rupiah)</label>
                        <input type="text" name="amount" id="tx_amount" class="form-input rupiah-input" placeholder="Contoh: 150.000" required>
                    </div>

                    <!-- Category Account Selector (Revenue vs Expense) -->
                    <div class="form-group" id="groupExpenseAccounts">
                        <label for="expense_account_id" class="form-label">Kategori Beban (Uang Keluar)</label>
                        <select name="account_id" id="expense_account_id" class="form-input form-select">
                            <option value="">-- Pilih Kategori Beban --</option>
                            @foreach ($expenseAccounts as $acc)
                                <option value="{{ $acc->id }}">{{ $acc->code }} - {{ $acc->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="form-group" id="groupRevenueAccounts" style="display: none;">
                        <label for="revenue_account_id" class="form-label">Kategori Pendapatan (Uang Masuk)</label>
                        <select name="account_id" id="revenue_account_id" class="form-input form-select" disabled>
                            <option value="">-- Pilih Kategori Pendapatan --</option>
                            @foreach ($revenueAccounts as $acc)
                                <option value="{{ $acc->id }}">{{ $acc->code }} - {{ $acc->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <!-- Reimbursement Fields -->
                    <div class="form-group" id="reimbursementCheckboxSection" style="margin-top: 14px;">
                        <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; font-weight: 600;">
                            <input type="checkbox" name="is_reimbursement" id="tx_is_reimbursement" value="1" onchange="toggleReimbursementFields()"> 
                            <span>Reimbursement (Penggantian Uang Karyawan)</span>
                        </label>
                    </div>

                    <div id="reimbursementDetailsSection" style="display: none; background: rgba(255, 255, 255, 0.05); padding: 12px; border-radius: 8px; border: 1px solid var(--border-glass); margin-top: 10px; margin-bottom: 14px;">
                        <div class="form-group" style="margin-bottom: 0;">
                            <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; font-weight: 500;">
                                <input type="checkbox" name="reimbursement_status" id="tx_reimbursement_status" value="transferred" onchange="toggleReimbursementTransferFields()"> 
                                <span>Sudah Ditransfer oleh Perusahaan</span>
                            </label>
                        </div>
                        
                        <div id="reimbursementTransferProofGroup" style="display: none; margin-top: 12px;">
                            <label class="form-label" style="margin-bottom: 6px;">Unggah Bukti Transfer Bank</label>
                            <input type="file" name="transfer_proof" id="tx_transfer_proof" class="form-input" accept="image/*,application/pdf">
                            <span id="transferProofUploadMsg" style="font-size: 0.75rem; color: var(--text-muted); display: block; margin-top: 2px;">Unggah bukti transfer bank. Max: 5MB</span>
                        </div>
                    </div>

                    <!-- Non-Reimbursement Transfer Status Checkbox & Proof Upload -->
                    <div class="form-group" id="nonReimbursementTransferSection" style="margin-top: 14px; margin-bottom: 14px;">
                        <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; font-weight: 500;">
                            <input type="checkbox" name="is_transferred" id="tx_is_transferred" value="1" checked onchange="toggleNonReimbursementTransferFields()"> 
                            <span id="label_tx_is_transferred">Sudah Ditransfer oleh Perusahaan</span>
                        </label>
                    </div>

                    <div id="nonReimburseTransferDetailsSection" style="display: none; background: rgba(255, 255, 255, 0.05); padding: 12px; border-radius: 8px; border: 1px solid var(--border-glass); margin-top: 10px; margin-bottom: 14px;">
                        <div class="form-group" style="margin-bottom: 0;">
                            <label class="form-label" style="margin-bottom: 6px;">Unggah Bukti Transfer Bank</label>
                            <input type="file" name="transfer_proof" id="tx_non_reimb_transfer_proof" class="form-input" accept="image/*,application/pdf">
                            <span id="nonReimbTransferProofUploadMsg" style="font-size: 0.75rem; color: var(--text-muted); display: block; margin-top: 2px;">Unggah bukti transfer bank. Max: 5MB</span>
                        </div>
                    </div>

                    <!-- Payment Asset Account Selector (Kas/Bank) -->
                    <div class="form-group" id="groupPaymentAccount">
                        <label for="payment_account_id" class="form-label">Sumber Kas / Bank</label>
                        <select name="payment_account_id" id="payment_account_id" class="form-input form-select" required>
                            @foreach ($paymentAccounts as $acc)
                                <option value="{{ $acc->id }}">{{ $acc->code }} - {{ $acc->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <!-- Keterangan / Deskripsi -->
                    <div class="form-group">
                        <label for="tx_description" class="form-label">Deskripsi / Keterangan</label>
                        <textarea name="description" id="tx_description" class="form-input" rows="3" placeholder="Tuliskan keterangan detail transaksi..."></textarea>
                    </div>

                    <!-- Upload Bukti Bon / Nota -->
                    <div class="form-group">
                        <label class="form-label">Unggah Bukti Bon / Kuitansi</label>
                        <div class="file-upload-wrapper">
                            <input type="file" name="receipt" id="tx_receipt" class="file-upload-input" accept="image/*,application/pdf" onchange="previewFile(event)">
                            <div class="file-upload-msg" id="fileUploadMsg">
                                Drag & Drop file foto / PDF di sini, atau <strong>pilih file</strong>
                            </div>
                        </div>
                        <!-- Preview Box -->
                        <div class="preview-container" id="previewContainer">
                            <img id="imagePreview" class="preview-img" src="#" alt="Pratinjau Bon">
                            <div id="pdfPreview" class="preview-pdf">
                                <svg style="width: 24px; height: 24px;" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
                                </svg>
                                <span id="pdfName">File Bukti.pdf</span>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" onclick="closeTransactionModal()" class="btn btn-secondary">Batal</button>
                    <button type="submit" id="submitBtn" class="btn btn-primary">Simpan Transaksi</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Receipt Display Modal -->
    <div id="receiptModal" class="modal-overlay">
        <div class="modal-card glass-panel modal-receipt-large">
            <div class="modal-header">
                <h3 id="receiptModalTitle">Bukti Transaksi</h3>
                <button onclick="closeReceiptModal()" class="modal-close">&times;</button>
            </div>
            <div class="modal-body" style="display: flex; justify-content: center; align-items: center; background: rgba(0,0,0,0.3); padding: 10px;">
                <!-- Full Viewer -->
                <div id="receiptViewerContainer" style="width: 100%; display: flex; justify-content: center;">
                    <!-- Dynamically populated -->
                </div>
            </div>
            <div class="modal-footer">
                <a id="receiptDownloadBtn" href="#" download class="btn btn-primary btn-sm">Unduh File</a>
                <button type="button" onclick="closeReceiptModal()" class="btn btn-secondary btn-sm">Tutup</button>
            </div>
        </div>
    </div>

    <!-- Modal: User Create/Edit (Owner Only) -->
    @if (Auth::user()->role === 'owner')
        <div id="userModal" class="modal-overlay">
            <div class="modal-card glass-panel">
                <div class="modal-header">
                    <h3 id="userModalTitle">Tambah User Baru</h3>
                    <button onclick="closeUserModal()" class="modal-close">&times;</button>
                </div>
                <form id="userForm" action="{{ route('web.users.store') }}" method="POST">
                    @csrf
                    <input type="hidden" name="_method" id="userFormMethod" value="POST">
                    <div class="modal-body">
                        <div class="form-group">
                            <label class="form-label">Nama Lengkap</label>
                            <input type="text" name="name" id="usr_name" class="form-input" required placeholder="Nama lengkap karyawan">
                        </div>
                        <div class="form-group" style="margin-top: 14px;">
                            <label class="form-label">Alamat Email</label>
                            <input type="email" name="email" id="usr_email" class="form-input" required placeholder="name@company.com">
                        </div>
                        <div class="form-group" style="margin-top: 14px;">
                            <label class="form-label" id="passwordLabel">Kata Sandi</label>
                            <input type="password" name="password" id="usr_password" class="form-input" required minlength="6" placeholder="Minimal 6 karakter">
                            <span id="passwordHelp" style="display:none; font-size:0.75rem; color:var(--text-muted); margin-top:2px;">Biarkan kosong jika tidak ingin mengganti sandi</span>
                        </div>
                        <div class="form-group" style="margin-top: 14px;">
                            <label class="form-label">Peran (Role)</label>
                            <select name="role" id="usr_role" class="form-input form-select" onchange="toggleUserPermissionsSection()" required>
                                <option value="staff" selected>Staff Finance</option>
                                <option value="owner">Owner</option>
                            </select>
                        </div>
                        <div class="form-group" id="userPermissionsSection" style="margin-top: 16px;">
                            <label class="form-label" style="margin-bottom: 8px;">Hak Akses Menu (Permissions)</label>
                            <div style="display: flex; flex-direction: column; gap: 8px; background: rgba(0,0,0,0.2); padding: 12px; border-radius: 8px; border: 1px solid var(--border-glass);">
                                <label style="display: flex; align-items: center; gap: 10px; cursor: pointer; font-size: 0.9rem;">
                                    <input type="checkbox" name="permissions[]" value="view_transactions" class="perm-checkbox" checked> Lihat Menu Transaksi
                                </label>
                                <label style="display: flex; align-items: center; gap: 10px; cursor: pointer; font-size: 0.9rem;">
                                    <input type="checkbox" name="permissions[]" value="create_transactions" class="perm-checkbox" checked> Tambah Transaksi
                                </label>
                                <label style="display: flex; align-items: center; gap: 10px; cursor: pointer; font-size: 0.9rem;">
                                    <input type="checkbox" name="permissions[]" value="edit_transactions" class="perm-checkbox"> Ubah Transaksi (Edit)
                                </label>
                                <label style="display: flex; align-items: center; gap: 10px; cursor: pointer; font-size: 0.9rem;">
                                    <input type="checkbox" name="permissions[]" value="delete_transactions" class="perm-checkbox"> Hapus Transaksi (Delete)
                                </label>
                                <hr style="border: 0; border-top: 1px solid var(--border-glass); margin: 6px 0;">
                                <label style="display: flex; align-items: center; gap: 10px; cursor: pointer; font-size: 0.9rem;">
                                    <input type="checkbox" name="permissions[]" value="view_settlements" class="perm-checkbox" checked> Lihat Menu Settlement
                                </label>
                                <label style="display: flex; align-items: center; gap: 10px; cursor: pointer; font-size: 0.9rem;">
                                    <input type="checkbox" name="permissions[]" value="create_settlements" class="perm-checkbox" checked> Tambah Settlement (Advance)
                                </label>
                                <label style="display: flex; align-items: center; gap: 10px; cursor: pointer; font-size: 0.9rem;">
                                    <input type="checkbox" name="permissions[]" value="process_settlements" class="perm-checkbox"> Proses Settlement (Settle)
                                </label>
                                <label style="display: flex; align-items: center; gap: 10px; cursor: pointer; font-size: 0.9rem;">
                                    <input type="checkbox" name="permissions[]" value="edit_settlements" class="perm-checkbox"> Ubah Settlement (Edit)
                                </label>
                                <label style="display: flex; align-items: center; gap: 10px; cursor: pointer; font-size: 0.9rem;">
                                    <input type="checkbox" name="permissions[]" value="delete_settlements" class="perm-checkbox"> Hapus Settlement (Delete)
                                </label>
                                <hr style="border: 0; border-top: 1px solid var(--border-glass); margin: 6px 0;">
                                <label style="display: flex; align-items: center; gap: 10px; cursor: pointer; font-size: 0.9rem;">
                                    <input type="checkbox" name="permissions[]" value="view_cash_advances" class="perm-checkbox" checked> Lihat Menu Cash Advance
                                </label>
                                <label style="display: flex; align-items: center; gap: 10px; cursor: pointer; font-size: 0.9rem;">
                                    <input type="checkbox" name="permissions[]" value="create_cash_advances" class="perm-checkbox" checked> Tambah Cash Advance (Pinjaman)
                                </label>
                                <label style="display: flex; align-items: center; gap: 10px; cursor: pointer; font-size: 0.9rem;">
                                    <input type="checkbox" name="permissions[]" value="edit_cash_advances" class="perm-checkbox"> Ubah Cash Advance (Edit)
                                </label>
                                <label style="display: flex; align-items: center; gap: 10px; cursor: pointer; font-size: 0.9rem;">
                                    <input type="checkbox" name="permissions[]" value="delete_cash_advances" class="perm-checkbox"> Hapus Cash Advance (Delete)
                                </label>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" onclick="closeUserModal()" class="btn btn-secondary">Batal</button>
                        <button type="submit" id="userSubmitBtn" class="btn btn-primary">Simpan User</button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    <!-- Modal: Advance Payment (Uang Muka) -->
    <div id="advanceModal" class="modal-overlay">
        <div class="modal-card glass-panel">
            <div class="modal-header">
                <h3>Buat Advance Baru</h3>
                <button onclick="closeAdvanceModal()" class="modal-close">&times;</button>
            </div>
            <form action="{{ route('web.settlements.storeAdvance') }}" method="POST">
                @csrf
                <div class="modal-body">
                    <div class="form-group">
                        <label class="form-label">Tanggal Pengeluaran Advance</label>
                        <input type="date" name="transaction_date" class="form-input" required value="{{ date('Y-m-d') }}">
                    </div>
                    <div class="form-group" style="margin-top: 14px;">
                        <label class="form-label">Nama Penerima Advance</label>
                        <input type="text" name="recipient_name" class="form-input" required placeholder="Contoh: Budi Santoso">
                    </div>
                    <div class="form-group" style="margin-top: 14px;">
                        <label class="form-label">Nominal Advance (Rupiah)</label>
                        <input type="text" name="amount" class="form-input rupiah-input" required placeholder="Contoh: 2.000.000">
                    </div>
                    <div class="form-group" style="margin-top: 14px;">
                        <label class="form-label">Sumber Dana (Kas/Bank Asal)</label>
                        <select name="payment_account_id" class="form-input form-select" required>
                            @foreach ($paymentAccounts as $acc)
                                <option value="{{ $acc->id }}">{{ $acc->code }} - {{ $acc->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group" style="margin-top: 14px;">
                        <label class="form-label">Tujuan Keperluan (Deskripsi)</label>
                        <textarea name="description" class="form-input" required rows="3" placeholder="Contoh: Pembelian server RAM baru PT Central" style="resize: none;"></textarea>
                    </div>
                    <div class="form-group" style="margin-top: 14px;">
                        <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; font-weight: 500;">
                            <input type="checkbox" name="is_transferred" value="1" checked> 
                            <span>Dana Advance Sudah Ditransfer / Diberikan</span>
                        </label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" onclick="closeAdvanceModal()" class="btn btn-secondary">Batal</button>
                    <button type="submit" class="btn btn-primary">Catat Advance</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal: Settle Advance (Laporkan Bon) -->
    <div id="settleModal" class="modal-overlay">
        <div class="modal-card glass-panel">
            <div class="modal-header">
                <h3>Form Settlement</h3>
                <button onclick="closeSettleModal()" class="modal-close">&times;</button>
            </div>
            <form id="settleAdvanceForm" action="" method="POST" enctype="multipart/form-data">
                @csrf
                <div class="modal-body">
                    <div class="form-group">
                        <label class="form-label">No. Bukti Advance</label>
                        <input type="text" id="settle_adv_num" class="form-input" disabled style="background: rgba(255,255,255,0.05); color: var(--text-secondary);">
                    </div>
                    <div class="form-group" style="margin-top: 12px;">
                        <label class="form-label">Nama Penerima Advance</label>
                        <input type="text" id="settle_adv_recipient" class="form-input" disabled style="background: rgba(255,255,255,0.05); color: var(--text-secondary);">
                    </div>
                    <div class="form-group" style="margin-top: 12px;">
                        <label class="form-label">Nominal Advance Awal</label>
                        <input type="text" id="settle_adv_amount" class="form-input" disabled style="background: rgba(255,255,255,0.05); color: var(--text-secondary);">
                    </div>
                    <hr style="border: 0; border-top: 1px solid var(--border-glass); margin: 16px 0;">
                    
                    <div class="form-group">
                        <label class="form-label">Nominal Riil Pembelian (Sesuai Bon)</label>
                        <input type="text" name="settlement_amount" id="settle_amount_input" class="form-input rupiah-input" required placeholder="Contoh: 1.800.000" oninput="calculateSettlementDiff()">
                        <span id="settlementDiffHelp" style="display: block; font-size: 0.8rem; margin-top: 4px; font-weight: 500;"></span>
                    </div>
                    <div class="form-group" style="margin-top: 14px;">
                        <label class="form-label">Akun Beban Akuntansi (Target Pembukuan)</label>
                        <select name="expense_account_id" class="form-input form-select" required>
                            <option value="" disabled selected>Pilih akun beban...</option>
                            @foreach ($expenseAccounts as $acc)
                                <option value="{{ $acc->id }}">{{ $acc->code }} - {{ $acc->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group" style="margin-top: 14px;">
                        <label class="form-label">Unggah Bukti Bon Fisik (Wajib Foto/PDF)</label>
                        <input type="file" name="receipt" class="form-input" required accept="image/*,application/pdf">
                        <span style="font-size: 0.75rem; color: var(--text-muted); display: block; margin-top: 2px;">Format: jpeg, png, jpg, pdf. Max: 5MB</span>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" onclick="closeSettleModal()" class="btn btn-secondary">Batal</button>
                    <button type="submit" class="btn btn-primary">Kirim Settlement</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal: Edit Settlement (Ubah Settlement) -->
    <div id="editSettlementModal" class="modal-overlay">
        <div class="modal-card glass-panel">
            <div class="modal-header">
                <h3>Ubah Data Settlement (Advance)</h3>
                <button onclick="closeEditSettlementModal()" class="modal-close">&times;</button>
            </div>
            <form id="editSettlementForm" action="" method="POST" enctype="multipart/form-data">
                @csrf
                @method('PUT')
                <div class="modal-body">
                    <!-- Basic Advance Fields (Always Visible) -->
                    <div class="form-group">
                        <label class="form-label">Tanggal Pengeluaran Advance</label>
                        <input type="date" name="transaction_date" id="edit_sett_date" class="form-input" required>
                    </div>
                    <div class="form-group" style="margin-top: 12px;">
                        <label class="form-label">Nama Penerima Advance</label>
                        <input type="text" name="recipient_name" id="edit_sett_recipient" class="form-input" required placeholder="Contoh: Budi Santoso">
                    </div>
                    <div class="form-group" style="margin-top: 12px;">
                        <label class="form-label">Nominal Advance (Rupiah)</label>
                        <input type="text" name="amount" id="edit_sett_amount" class="form-input rupiah-input" required placeholder="Contoh: 2.000.000" oninput="calculateEditSettlementDiff()">
                    </div>
                    <div class="form-group" style="margin-top: 12px;">
                        <label class="form-label">Sumber Dana (Kas/Bank Asal)</label>
                        <select name="payment_account_id" id="edit_sett_payment_account" class="form-input form-select" required>
                            @foreach ($paymentAccounts as $acc)
                                <option value="{{ $acc->id }}">{{ $acc->code }} - {{ $acc->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group" style="margin-top: 12px;">
                        <label class="form-label">Tujuan Keperluan (Deskripsi)</label>
                        <textarea name="description" id="edit_sett_description" class="form-input" required rows="3" style="resize: none;"></textarea>
                    </div>

                    <!-- Settlement Info (Only Visible if Already Settled) -->
                    <div id="editSettlementDetailsSection" style="display: none;">
                        <hr style="border: 0; border-top: 1px solid var(--border-glass); margin: 16px 0;">
                        <div class="form-group">
                            <label class="form-label">Nominal Riil Pembelian (Sesuai Bon)</label>
                            <input type="text" name="settlement_amount" id="edit_settle_amount_input" class="form-input rupiah-input" placeholder="Contoh: 1.800.000" oninput="calculateEditSettlementDiff()">
                            <span id="editSettlementDiffHelp" style="display: block; font-size: 0.8rem; margin-top: 4px; font-weight: 500;"></span>
                        </div>
                        <div class="form-group" style="margin-top: 12px;">
                            <label class="form-label">Akun Beban Akuntansi (Target Pembukuan)</label>
                            <select name="expense_account_id" id="edit_sett_expense_account" class="form-input form-select">
                                <option value="" disabled selected>Pilih akun beban...</option>
                                @foreach ($expenseAccounts as $acc)
                                    <option value="{{ $acc->id }}">{{ $acc->code }} - {{ $acc->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group" style="margin-top: 12px;">
                            <label class="form-label">Ubah Bukti Bon Fisik (Opsional)</label>
                            <input type="file" name="receipt" class="form-input" accept="image/*,application/pdf">
                            <span style="font-size: 0.75rem; color: var(--text-muted); display: block; margin-top: 2px;">Kosongkan jika tidak ingin mengubah bukti bon. Max: 5MB</span>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" onclick="closeEditSettlementModal()" class="btn btn-secondary">Batal</button>
                    <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal: Cash Advance Loan (Pinjaman Karyawan) -->
    <div id="loanModal" class="modal-overlay">
        <div class="modal-card glass-panel">
            <div class="modal-header">
                <h3>Buat Pinjaman Baru</h3>
                <button onclick="closeLoanModal()" class="modal-close">&times;</button>
            </div>
            <form action="{{ route('web.cash_advances.store') }}" method="POST">
                @csrf
                <div class="modal-body">
                    <div class="form-group">
                        <label class="form-label">Tanggal Pinjaman</label>
                        <input type="date" name="transaction_date" class="form-input" required value="{{ date('Y-m-d') }}">
                    </div>
                    <div class="form-group" style="margin-top: 14px;">
                        <label class="form-label">Nama Penerima (Karyawan)</label>
                        <input type="text" name="recipient_name" class="form-input" required placeholder="Contoh: Budi Santoso">
                    </div>
                    <div class="form-group" style="margin-top: 14px;">
                        <label class="form-label">Nominal Pinjaman (Rupiah)</label>
                        <input type="text" name="amount" class="form-input rupiah-input" required placeholder="Contoh: 2.000.000">
                    </div>
                    <div class="form-group" style="margin-top: 14px;">
                        <label class="form-label">Sumber Dana (Kas/Bank Asal)</label>
                        <select name="payment_account_id" class="form-input form-select" required>
                            @foreach ($paymentAccounts as $acc)
                                <option value="{{ $acc->id }}">{{ $acc->code }} - {{ $acc->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group" style="margin-top: 14px;">
                        <label class="form-label">Keterangan / Alasan Pinjaman</label>
                        <textarea name="description" class="form-input" required rows="3" placeholder="Contoh: Pinjaman darurat biaya rumah sakit" style="resize: none;"></textarea>
                    </div>
                    <div class="form-group" style="margin-top: 14px;">
                        <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; font-weight: 500;">
                            <input type="checkbox" name="is_transferred" value="1" checked> 
                            <span>Dana Pinjaman Sudah Ditransfer / Diberikan</span>
                        </label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" onclick="closeLoanModal()" class="btn btn-secondary">Batal</button>
                    <button type="submit" class="btn btn-primary">Catat Pinjaman</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal: Pay Loan Repayment (Bayar Angsuran) -->
    <div id="repaymentModal" class="modal-overlay">
        <div class="modal-card glass-panel">
            <div class="modal-header">
                <h3>Bayar Angsuran Pinjaman</h3>
                <button onclick="closeRepaymentModal()" class="modal-close">&times;</button>
            </div>
            <form id="repaymentForm" action="" method="POST">
                @csrf
                <div class="modal-body">
                    <div class="form-group">
                        <label class="form-label">No. Bukti Pinjaman</label>
                        <input type="text" id="repay_loan_num" class="form-input" disabled style="background: rgba(255,255,255,0.05); color: var(--text-secondary);">
                    </div>
                    <div class="form-group" style="margin-top: 12px;">
                        <label class="form-label">Nama Karyawan</label>
                        <input type="text" id="repay_loan_recipient" class="form-input" disabled style="background: rgba(255,255,255,0.05); color: var(--text-secondary);">
                    </div>
                    <div class="form-group" style="margin-top: 12px;">
                        <label class="form-label">Sisa Pinjaman Saat Ini</label>
                        <input type="text" id="repay_loan_remaining" class="form-input" disabled style="background: rgba(255,255,255,0.05); color: var(--text-secondary);">
                    </div>
                    <hr style="border: 0; border-top: 1px solid var(--border-glass); margin: 16px 0;">

                    <div class="form-group">
                        <label class="form-label">Tanggal Pembayaran</label>
                        <input type="date" name="transaction_date" class="form-input" required value="{{ date('Y-m-d') }}">
                    </div>
                    <div class="form-group" style="margin-top: 14px;">
                        <label class="form-label">Nominal Angsuran (Rupiah)</label>
                        <input type="text" name="amount" id="repayment_amount_input" class="form-input rupiah-input" required placeholder="Contoh: 500.000">
                    </div>
                    <div class="form-group" style="margin-top: 14px;">
                        <label class="form-label">Diterima Di (Akun Kas/Bank Penerima)</label>
                        <select name="payment_account_id" class="form-input form-select" required>
                            @foreach ($paymentAccounts as $acc)
                                <option value="{{ $acc->id }}">{{ $acc->code }} - {{ $acc->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group" style="margin-top: 14px;">
                        <label class="form-label">Keterangan</label>
                        <textarea name="description" class="form-input" required rows="2" placeholder="Contoh: Pembayaran angsuran cash advance ke-1" style="resize: none;"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" onclick="closeRepaymentModal()" class="btn btn-secondary">Batal</button>
                    <button type="submit" class="btn btn-primary">Catat Angsuran</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal: Edit Cash Advance Loan (Ubah Pinjaman) -->
    <div id="editLoanModal" class="modal-overlay">
        <div class="modal-card glass-panel">
            <div class="modal-header">
                <h3>Ubah Pinjaman Cash Advance</h3>
                <button onclick="closeEditLoanModal()" class="modal-close">&times;</button>
            </div>
            <form id="editLoanForm" action="" method="POST">
                @csrf
                @method('PUT')
                <div class="modal-body">
                    <div class="form-group">
                        <label class="form-label">Tanggal Pinjaman</label>
                        <input type="date" name="transaction_date" id="edit_loan_date" class="form-input" required>
                    </div>
                    <div class="form-group" style="margin-top: 14px;">
                        <label class="form-label">Nama Penerima (Karyawan)</label>
                        <input type="text" name="recipient_name" id="edit_loan_recipient" class="form-input" required placeholder="Contoh: Budi Santoso">
                    </div>
                    <div class="form-group" style="margin-top: 14px;">
                        <label class="form-label">Nominal Pinjaman (Rupiah)</label>
                        <input type="text" name="amount" id="edit_loan_amount" class="form-input rupiah-input" required placeholder="Contoh: 2.000.000">
                    </div>
                    <div class="form-group" style="margin-top: 14px;">
                        <label class="form-label">Sumber Dana (Kas/Bank Asal)</label>
                        <select name="payment_account_id" id="edit_loan_payment_account" class="form-input form-select" required>
                            @foreach ($paymentAccounts as $acc)
                                <option value="{{ $acc->id }}">{{ $acc->code }} - {{ $acc->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group" style="margin-top: 14px;">
                        <label class="form-label">Keterangan / Alasan Pinjaman</label>
                        <textarea name="description" id="edit_loan_description" class="form-input" required rows="3" style="resize: none;"></textarea>
                    </div>

                    <!-- Status Transfer Checkbox & Nominal Transfer -->
                    <div class="form-group" style="margin-top: 14px; margin-bottom: 14px;">
                        <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; font-weight: 500;">
                            <input type="checkbox" name="is_transferred" id="edit_loan_is_transferred" value="1" onchange="toggleEditLoanTransferFields()"> 
                            <span>Sudah Ditransfer oleh Perusahaan</span>
                        </label>
                    </div>

                    <div id="editLoanTransferDetailsSection" style="display: none; background: rgba(255, 255, 255, 0.05); padding: 12px; border-radius: 8px; border: 1px solid var(--border-glass); margin-top: 10px; margin-bottom: 14px;">
                        <div class="form-group" style="margin-bottom: 0;">
                            <label class="form-label" style="margin-bottom: 6px;">Nominal yang Ditransfer (Rupiah)</label>
                            <input type="text" name="transferred_amount" id="edit_loan_transferred_amount" class="form-input rupiah-input" placeholder="Contoh: 2.500.000">
                            <span style="font-size: 0.75rem; color: var(--text-muted); display: block; margin-top: 2px;">Masukkan nominal riil yang ditransfer. Kosongkan untuk menyamakan dengan nominal pinjaman.</span>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" onclick="closeEditLoanModal()" class="btn btn-secondary">Batal</button>
                    <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal: Bayar Reimbursement -->
    <div id="reimburseTransferModal" class="modal-overlay">
        <div class="modal-card glass-panel">
            <div class="modal-header">
                <h3>Bayar Reimbursement</h3>
                <button onclick="closeReimburseTransferModal()" class="modal-close">&times;</button>
            </div>
            <form id="reimburseTransferForm" action="" method="POST" enctype="multipart/form-data">
                @csrf
                <div class="modal-body">
                    <p style="font-size: 0.9rem; color: var(--text-muted); margin-bottom: 16px;">
                        Tandai reimbursement <strong id="reimburseTxNumber">TX-XXXX</strong> sebesar <strong id="reimburseTxAmount">Rp 0</strong> sudah ditransfer dan unggah buktinya.
                    </p>
                    <div class="form-group">
                        <label class="form-label">Tanggal Transfer</label>
                        <input type="date" name="transfer_date" class="form-input" required value="{{ date('Y-m-d') }}">
                    </div>
                    <div class="form-group" style="margin-top: 14px;">
                        <label class="form-label">Sumber Kas / Bank Pembayar</label>
                        <select name="payment_account_id" class="form-input form-select" required>
                            @foreach ($paymentAccounts as $acc)
                                <option value="{{ $acc->id }}">{{ $acc->code }} - {{ $acc->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group" style="margin-top: 14px;">
                        <label class="form-label">Unggah Bukti Transfer Bank</label>
                        <input type="file" name="transfer_proof" class="form-input" accept="image/*,application/pdf" required>
                        <span style="font-size: 0.75rem; color: var(--text-muted); display: block; margin-top: 2px;">Wajib mengunggah bukti transfer fisik. Max: 5MB</span>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" onclick="closeReimburseTransferModal()" class="btn btn-secondary">Batal</button>
                    <button type="submit" class="btn btn-success">Konfirmasi Sudah Ditransfer</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Hidden form for deleting single loan and repayments -->
    <form id="deleteLoanForm" action="" method="POST" style="display: none;">
        @csrf
        @method('DELETE')
    </form>
    <form id="deleteRepaymentForm" action="" method="POST" style="display: none;">
        @csrf
        @method('DELETE')
    </form>

    <script>
        // ── Rupiah Input Formatting ──
        function formatRupiah(angka, prefix) {
            var number_string = angka.replace(/[^,\d]/g, '').toString(),
                split = number_string.split(','),
                sisa = split[0].length % 3,
                rupiah = split[0].substr(0, sisa),
                ribuan = split[0].substr(sisa).match(/\d{3}/gi);

            if (ribuan) {
                var separator = sisa ? '.' : '';
                rupiah += separator + ribuan.join('.');
            }

            rupiah = split[1] != undefined ? rupiah + ',' + split[1] : rupiah;
            return prefix == undefined ? rupiah : (rupiah ? 'Rp ' + rupiah : '');
        }

        document.addEventListener('DOMContentLoaded', () => {
            // Apply formatting on input for all .rupiah-input fields
            document.querySelectorAll('.rupiah-input').forEach(input => {
                input.addEventListener('input', function(e) {
                    this.value = formatRupiah(this.value);
                });
            });

            // Strip formatting dots before form submission
            document.querySelectorAll('form').forEach(form => {
                form.addEventListener('submit', function() {
                    this.querySelectorAll('.rupiah-input').forEach(input => {
                        input.value = input.value.replace(/\./g, '');
                    });
                });
            });
        });

        // SPA Sidebar Tab Switching Logic
        function switchTab(tabName) {
            // Dismiss any active flash notification on tab switch
            const existingAlert = document.getElementById('flashAlert');
            if (existingAlert) dismissAlert();

            // Redirect old tab names to sub-tabs under transactions
            let targetSubTab = null;
            if (tabName === 'settlements') {
                tabName = 'transactions';
                targetSubTab = 'settlements';
            } else if (tabName === 'cash-advances') {
                tabName = 'transactions';
                targetSubTab = 'cash-advances';
            }

            const tabs = ['dashboard', 'transactions', 'ledger', 'users', 'settings'];
            tabs.forEach(t => {
                const link = document.getElementById('nav-' + t);
                const section = document.getElementById('section-' + t);
                if (link) {
                    if (t === tabName) {
                        link.classList.add('active');
                    } else {
                        link.classList.remove('active');
                    }
                }
                if (section) {
                    if (t === tabName) {
                        section.style.display = 'block';
                    } else {
                        section.style.display = 'none';
                    }
                }
            });

            // Update content title dynamically
            const titleEl = document.getElementById('content-title');
            if (titleEl) {
                if (tabName === 'dashboard') titleEl.innerText = 'Dashboard Keuangan';
                else if (tabName === 'ledger') titleEl.innerText = 'Buku Besar / General Ledger';
                else if (tabName === 'users') titleEl.innerText = 'User & Hak Akses';
                else if (tabName === 'settings') titleEl.innerText = 'Pengaturan (COA)';
            }
            
            // Switch to correct sub-tab if in transactions section
            if (tabName === 'transactions') {
                if (targetSubTab) {
                    switchSubTab(targetSubTab);
                } else {
                    const urlParams = new URLSearchParams(window.location.search);
                    const subTabParam = urlParams.get('subTab') || 'transactions';
                    switchSubTab(subTabParam);
                }
            } else {
                // Update URL for main tabs
                const url = new URL(window.location);
                url.searchParams.set('activeTab', tabName);
                url.searchParams.delete('subTab');
                window.history.pushState({}, '', url);
            }
        }

        // Sub-Tab Switching Logic inside Transactions page
        function switchSubTab(subTabName) {
            const subTabs = ['transactions', 'settlements', 'cash-advances'];
            subTabs.forEach(s => {
                const button = document.getElementById('sub-nav-' + s);
                const panel = document.getElementById('sub-section-' + s);
                if (button) {
                    if (s === subTabName) {
                        button.classList.add('active');
                    } else {
                        button.classList.remove('active');
                    }
                }
                if (panel) {
                    if (s === subTabName) {
                        panel.classList.add('active');
                        panel.style.display = 'block';
                    } else {
                        panel.classList.remove('active');
                        panel.style.display = 'none';
                    }
                }
            });

            // Update content title based on active sub-tab
            const titleEl = document.getElementById('content-title');
            if (titleEl) {
                if (subTabName === 'transactions') titleEl.innerText = 'Rincian Transaksi';
                else if (subTabName === 'settlements') titleEl.innerText = 'Settlement';
                else if (subTabName === 'cash-advances') titleEl.innerText = 'Cash Advance';
            }

            // Update URL parameters
            const url = new URL(window.location);
            url.searchParams.set('activeTab', 'transactions');
            url.searchParams.set('subTab', subTabName);
            window.history.pushState({}, '', url);
        }

        // Auto-dismiss flash notification after 5 seconds
        window.addEventListener('DOMContentLoaded', () => {
            const flashAlert = document.getElementById('flashAlert');
            if (flashAlert) {
                setTimeout(dismissAlert, 5000);
            }
        });

        // Check if filtering is active, default to transactions tab on load
        window.addEventListener('DOMContentLoaded', () => {
            const activeTabFromBackend = "{{ $activeTab ?? '' }}";
            const urlParams = new URLSearchParams(window.location.search);
            const activeTabFromUrl = urlParams.get('activeTab');

            if (activeTabFromBackend && activeTabFromBackend !== 'dashboard') {
                switchTab(activeTabFromBackend);
            } else if (activeTabFromUrl) {
                switchTab(activeTabFromUrl);
            } else if (urlParams.has('ledger_account_id')) {
                switchTab('ledger');
            } else if (urlParams.has('start_date') || urlParams.has('end_date') || urlParams.has('page')) {
                switchTab('transactions');
            } else {
                switchTab('dashboard');
            }
        });

        // Theme Toggle Logic
        const themeToggleBtn = document.getElementById('themeToggleBtn');
        
        function updateThemeIcon(theme) {
            if (theme === 'light') {
                // Sun Icon (light mode active, click for dark mode)
                themeToggleBtn.innerHTML = `
                    <svg style="width: 20px; height: 20px;" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364-6.364l-.707.707M6.364 17.636l-.707.707M18.364 17.636l-.707-.707M6.364 6.364l-.707-.707M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                    </svg>
                `;
                themeToggleBtn.title = "Ganti ke Dark Mode (Hitam)";
            } else {
                // Moon Icon (dark mode active, click for light mode)
                themeToggleBtn.innerHTML = `
                    <svg style="width: 20px; height: 20px;" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z" />
                    </svg>
                `;
                themeToggleBtn.title = "Ganti ke Light Mode";
            }
        }
        
        // Initial Theme Load
        const currentTheme = document.documentElement.getAttribute('data-theme') || 'dark';
        updateThemeIcon(currentTheme);

        themeToggleBtn.addEventListener('click', () => {
            const activeTheme = document.documentElement.getAttribute('data-theme') || 'dark';
            const newTheme = activeTheme === 'dark' ? 'light' : 'dark';
            
            document.documentElement.setAttribute('data-theme', newTheme);
            localStorage.setItem('theme', newTheme);
            updateThemeIcon(newTheme);
        });

        // Modal Handlers
        const transactionModal = document.getElementById('transactionModal');
        const receiptModal = document.getElementById('receiptModal');
        const transactionForm = document.getElementById('transactionForm');
        const formMethod = document.getElementById('formMethod');
        const submitBtn = document.getElementById('submitBtn');

        function openTransactionModal(type) {
            const modalTitle = document.getElementById('modalTitle');
            const txType = document.getElementById('txType');
            const groupExpense = document.getElementById('groupExpenseAccounts');
            const groupRevenue = document.getElementById('groupRevenueAccounts');
            const selectExpense = document.getElementById('expense_account_id');
            const selectRevenue = document.getElementById('revenue_account_id');
            
            // Set dynamic form parameters for CREATE
            formMethod.value = 'POST';
            transactionForm.action = "{{ route('web.transactions.store') }}";
            submitBtn.innerText = 'Simpan Transaksi';
            txType.value = type;

            // Clear values
            document.getElementById('tx_date').value = "{{ date('Y-m-d') }}";
            document.getElementById('tx_amount').value = '';
            document.getElementById('tx_description').value = '';
            selectExpense.value = '';
            selectRevenue.value = '';

            // Reset Reimbursement fields
            const checkboxIsReimbursement = document.getElementById('tx_is_reimbursement');
            const checkboxReimbursementStatus = document.getElementById('tx_reimbursement_status');
            const fileTransferProof = document.getElementById('tx_transfer_proof');
            const transferProofMsg = document.getElementById('transferProofUploadMsg');
            const reimbursementSection = document.getElementById('reimbursementCheckboxSection');

            if (checkboxIsReimbursement) checkboxIsReimbursement.checked = false;
            if (checkboxReimbursementStatus) checkboxReimbursementStatus.checked = false;
            if (fileTransferProof) fileTransferProof.value = '';
            if (transferProofMsg) transferProofMsg.innerHTML = 'Unggah bukti transfer bank. Max: 5MB';

            // Reset File input preview
            document.getElementById('tx_receipt').value = '';
            document.getElementById('fileUploadMsg').innerHTML = 'Drag & Drop file foto / PDF di sini, atau <strong>pilih file</strong>';
            document.getElementById('previewContainer').style.display = 'none';

            if (type === 'in') {
                modalTitle.innerText = 'Tambah Uang Masuk';
                
                groupExpense.style.display = 'none';
                selectExpense.disabled = true;
                selectExpense.removeAttribute('required');

                groupRevenue.style.display = 'block';
                selectRevenue.disabled = false;
                selectRevenue.setAttribute('required', 'required');

                if (reimbursementSection) reimbursementSection.style.display = 'none';
            } else {
                modalTitle.innerText = 'Tambah Uang Keluar';

                groupRevenue.style.display = 'none';
                selectRevenue.disabled = true;
                selectRevenue.removeAttribute('required');

                groupExpense.style.display = 'block';
                selectExpense.disabled = false;
                selectExpense.setAttribute('required', 'required');

                if (reimbursementSection) reimbursementSection.style.display = 'block';
            }

            toggleReimbursementFields();
            transactionModal.classList.add('active');
        }

        function openEditTransactionModal(tx) {
            const modalTitle = document.getElementById('modalTitle');
            const txType = document.getElementById('txType');
            const groupExpense = document.getElementById('groupExpenseAccounts');
            const groupRevenue = document.getElementById('groupRevenueAccounts');
            const selectExpense = document.getElementById('expense_account_id');
            const selectRevenue = document.getElementById('revenue_account_id');
            const selectPayment = document.getElementById('payment_account_id');

            // Set dynamic form parameters for EDIT
            formMethod.value = 'PUT';
            transactionForm.action = `/transactions/${tx.id}`;
            submitBtn.innerText = 'Perbarui Transaksi';
            txType.value = tx.type;

            // Populate form values
            document.getElementById('tx_date').value = tx.date;
            document.getElementById('tx_amount').value = formatRupiah(tx.amount.toString());
            document.getElementById('tx_description').value = tx.description || '';
            selectPayment.value = tx.paymentAccountId || '';

            // Populate Reimbursement fields
            const checkboxIsReimbursement = document.getElementById('tx_is_reimbursement');
            const checkboxReimbursementStatus = document.getElementById('tx_reimbursement_status');
            const fileTransferProof = document.getElementById('tx_transfer_proof');
            const transferProofMsg = document.getElementById('transferProofUploadMsg');
            const reimbursementSection = document.getElementById('reimbursementCheckboxSection');

            if (checkboxIsReimbursement) checkboxIsReimbursement.checked = tx.isReimbursement;
            if (checkboxReimbursementStatus) checkboxReimbursementStatus.checked = (tx.reimbursementStatus === 'transferred');
            if (fileTransferProof) fileTransferProof.value = '';
            
            const checkboxIsTransferred = document.getElementById('tx_is_transferred');
            if (checkboxIsTransferred) {
                checkboxIsTransferred.checked = (tx.isTransferred === true || tx.isTransferred === 1 || tx.isTransferred === "1");
            }

            const nonReimbProofMsg = document.getElementById('nonReimbTransferProofUploadMsg');
            const fileNonReimbTransferProof = document.getElementById('tx_non_reimb_transfer_proof');
            if (fileNonReimbTransferProof) fileNonReimbTransferProof.value = '';

            if (tx.hasTransferProof) {
                if (tx.isReimbursement) {
                    if (transferProofMsg) transferProofMsg.innerHTML = `Bukti transfer saat ini: <strong>${tx.transferProofName}</strong><br><span style="font-size: 0.8rem; color: var(--text-muted);">Pilih file baru jika ingin mengganti</span>`;
                    if (nonReimbProofMsg) nonReimbProofMsg.innerHTML = 'Unggah bukti transfer bank. Max: 5MB';
                } else {
                    if (nonReimbProofMsg) nonReimbProofMsg.innerHTML = `Bukti transfer saat ini: <strong>${tx.transferProofName}</strong><br><span style="font-size: 0.8rem; color: var(--text-muted);">Pilih file baru jika ingin mengganti</span>`;
                    if (transferProofMsg) transferProofMsg.innerHTML = 'Unggah bukti transfer bank. Max: 5MB';
                }
            } else {
                if (transferProofMsg) transferProofMsg.innerHTML = 'Unggah bukti transfer bank. Max: 5MB';
                if (nonReimbProofMsg) nonReimbProofMsg.innerHTML = 'Unggah bukti transfer bank. Max: 5MB';
            }

            toggleNonReimbursementTransferFields();

            // Reset File input value
            document.getElementById('tx_receipt').value = '';

            if (tx.type === 'in') {
                modalTitle.innerText = 'Ubah Transaksi Uang Masuk';
                
                groupExpense.style.display = 'none';
                selectExpense.disabled = true;
                selectExpense.removeAttribute('required');

                groupRevenue.style.display = 'block';
                selectRevenue.disabled = false;
                selectRevenue.setAttribute('required', 'required');
                selectRevenue.value = tx.categoryId;

                if (reimbursementSection) reimbursementSection.style.display = 'none';
            } else {
                modalTitle.innerText = 'Ubah Transaksi Uang Keluar';

                groupRevenue.style.display = 'none';
                selectRevenue.disabled = true;
                selectRevenue.removeAttribute('required');

                groupExpense.style.display = 'block';
                selectExpense.disabled = false;
                selectExpense.setAttribute('required', 'required');
                selectExpense.value = tx.categoryId;

                if (reimbursementSection) reimbursementSection.style.display = 'block';
            }

            toggleReimbursementFields();

            // Pre-populate receipt preview
            const uploadMsg = document.getElementById('fileUploadMsg');
            const container = document.getElementById('previewContainer');
            const imgPreview = document.getElementById('imagePreview');
            const pdfPreview = document.getElementById('pdfPreview');
            const pdfName = document.getElementById('pdfName');

            if (tx.hasReceipt) {
                uploadMsg.innerHTML = `Bukti saat ini: <strong>${tx.receiptName}</strong><br><span style="font-size: 0.8rem; color: var(--text-muted);">Pilih file baru jika ingin mengganti</span>`;
                
                const isPdf = tx.receiptUrl.toLowerCase().endsWith('.pdf');
                if (isPdf) {
                    pdfName.innerText = tx.receiptName;
                    imgPreview.style.display = 'none';
                    pdfPreview.style.display = 'flex';
                    container.style.display = 'block';
                } else {
                    imgPreview.src = tx.receiptUrl;
                    imgPreview.style.display = 'block';
                    pdfPreview.style.display = 'none';
                    container.style.display = 'block';
                }
            } else {
                uploadMsg.innerHTML = 'Drag & Drop file foto / PDF di sini, atau <strong>pilih file</strong>';
                container.style.display = 'none';
            }

            transactionModal.classList.add('active');
        }

        function closeTransactionModal() {
            transactionModal.classList.remove('active');
        }

        // Toggles visibility of the reimbursement check details
        function toggleReimbursementFields() {
            const isReimbursementCheckbox = document.getElementById('tx_is_reimbursement');
            const detailsSection = document.getElementById('reimbursementDetailsSection');
            const nonReimburseTransfer = document.getElementById('nonReimbursementTransferSection');
            
            if (isReimbursementCheckbox && isReimbursementCheckbox.checked) {
                if (detailsSection) detailsSection.style.display = 'block';
                if (nonReimburseTransfer) nonReimburseTransfer.style.display = 'none';
            } else {
                if (detailsSection) detailsSection.style.display = 'none';
                if (nonReimburseTransfer) nonReimburseTransfer.style.display = 'block';
                const statusCheckbox = document.getElementById('tx_reimbursement_status');
                if (statusCheckbox) statusCheckbox.checked = false;
            }
            toggleReimbursementTransferFields();
            toggleNonReimbursementTransferFields();
        }

        // Toggles visibility of normal transaction transfer proof section
        function toggleNonReimbursementTransferFields() {
            const isReimbursementCheckbox = document.getElementById('tx_is_reimbursement');
            const isTransferredCheckbox = document.getElementById('tx_is_transferred');
            const detailsSection = document.getElementById('nonReimburseTransferDetailsSection');
            const fileInput = document.getElementById('tx_non_reimb_transfer_proof');

            if (isReimbursementCheckbox && !isReimbursementCheckbox.checked) {
                if (isTransferredCheckbox && isTransferredCheckbox.checked) {
                    if (detailsSection) detailsSection.style.display = 'block';
                } else {
                    if (detailsSection) detailsSection.style.display = 'none';
                    if (fileInput) fileInput.value = '';
                }
            } else {
                if (detailsSection) detailsSection.style.display = 'none';
            }
        }

        // Toggles required attributes & visibility of source Kas/Bank and transfer proof file
        function toggleReimbursementTransferFields() {
            const isReimbursementCheckbox = document.getElementById('tx_is_reimbursement');
            const statusCheckbox = document.getElementById('tx_reimbursement_status');
            const proofGroup = document.getElementById('reimbursementTransferProofGroup');
            const paymentGroup = document.getElementById('groupPaymentAccount');
            const paymentSelect = document.getElementById('payment_account_id');
            const fileInput = document.getElementById('tx_transfer_proof');

            if (isReimbursementCheckbox && isReimbursementCheckbox.checked) {
                if (statusCheckbox && statusCheckbox.checked) {
                    if (proofGroup) proofGroup.style.display = 'block';
                    if (paymentGroup) paymentGroup.style.display = 'block';
                    if (paymentSelect) {
                        paymentSelect.setAttribute('required', 'required');
                        paymentSelect.disabled = false;
                    }
                } else {
                    if (proofGroup) proofGroup.style.display = 'none';
                    if (paymentGroup) paymentGroup.style.display = 'none';
                    if (paymentSelect) {
                        paymentSelect.removeAttribute('required');
                        paymentSelect.disabled = true;
                    }
                    if (fileInput) fileInput.value = '';
                }
            } else {
                if (proofGroup) proofGroup.style.display = 'none';
                if (paymentGroup) paymentGroup.style.display = 'block';
                if (paymentSelect) {
                    paymentSelect.setAttribute('required', 'required');
                    paymentSelect.disabled = false;
                }
                if (fileInput) fileInput.value = '';
            }
        }

        // Action modal handlers for marking pending reimbursement as transferred
        function openReimburseTransferModal(id, number, amount) {
            const form = document.getElementById('reimburseTransferForm');
            form.action = `/transactions/${id}/transfer-reimburse`;
            document.getElementById('reimburseTxNumber').innerText = number;
            document.getElementById('reimburseTxAmount').innerText = 'Rp ' + formatRupiah(amount.toString());
            document.getElementById('reimburseTransferModal').classList.add('active');
        }

        function closeReimburseTransferModal() {
            document.getElementById('reimburseTransferModal').classList.remove('active');
        }

        // Live File Upload Preview
        function previewFile(event) {
            const input = event.target;
            const container = document.getElementById('previewContainer');
            const imgPreview = document.getElementById('imagePreview');
            const pdfPreview = document.getElementById('pdfPreview');
            const pdfName = document.getElementById('pdfName');
            const uploadMsg = document.getElementById('fileUploadMsg');

            if (input.files && input.files[0]) {
                const file = input.files[0];
                uploadMsg.innerHTML = `File terpilih: <strong>${file.name}</strong>`;

                if (file.type.startsWith('image/')) {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        imgPreview.src = e.target.result;
                        imgPreview.style.display = 'block';
                        pdfPreview.style.display = 'none';
                        container.style.display = 'block';
                    };
                    reader.readAsDataURL(file);
                } else if (file.type === 'application/pdf') {
                    pdfName.innerText = file.name;
                    imgPreview.style.display = 'none';
                    pdfPreview.style.display = 'flex';
                    container.style.display = 'block';
                }
            }
        }

        // Receipt Preview Modal
        function openReceiptModal(url, originalName) {
            const receiptViewerContainer = document.getElementById('receiptViewerContainer');
            const downloadBtn = document.getElementById('receiptDownloadBtn');
            const title = document.getElementById('receiptModalTitle');

            title.innerText = `Bukti Transaksi: ${originalName}`;
            downloadBtn.href = url;
            downloadBtn.setAttribute('download', originalName);

            const isPdf = url.toLowerCase().endsWith('.pdf');

            if (isPdf) {
                receiptViewerContainer.innerHTML = `<iframe class="receipt-frame" src="${url}"></iframe>`;
            } else {
                receiptViewerContainer.innerHTML = `<img class="receipt-image-large" src="${url}" alt="Bukti Bon">`;
            }

            receiptModal.classList.add('active');
        }

        function closeReceiptModal() {
            receiptModal.classList.remove('active');
            document.getElementById('receiptViewerContainer').innerHTML = '';
        }

        // Confirm Delete
        function confirmDeleteTransaction(id, transactionNumber) {
            if (confirm(`Apakah Anda yakin ingin menghapus transaksi ${transactionNumber}?`)) {
                const form = document.getElementById('deleteTransactionForm');
                form.action = `/transactions/${id}`;

                // Add activeTab to keep user on the same tab
                let tabInput = document.createElement('input');
                tabInput.type = 'hidden';
                tabInput.name = 'activeTab';
                
                // Determine if we are on settlements or transactions tab
                const isSettlements = document.getElementById('nav-settlements').classList.contains('active');
                tabInput.value = isSettlements ? 'settlements' : 'transactions';
                
                form.appendChild(tabInput);
                form.submit();
            }
        }

        // Safe Edit Initiation Handler
        function initiateEdit(btn) {
            try {
                const tx = JSON.parse(btn.getAttribute('data-tx'));
                openEditTransactionModal(tx);
            } catch (e) {
                console.error("Gagal melakukan parse data transaksi: ", e);
                alert("Terjadi kesalahan saat memuat data transaksi.");
            }
        }

        // Bulk Delete Handlers (Owner Only)
        function toggleCheckAllTx(master) {
            const checkboxes = document.querySelectorAll('.tx-checkbox');
            checkboxes.forEach(cb => {
                cb.checked = master.checked;
            });
            updateBulkDeleteState();
        }

        function updateBulkDeleteState() {
            const checkboxes = document.querySelectorAll('.tx-checkbox');
            const selectedIds = [];
            checkboxes.forEach(cb => {
                if (cb.checked) {
                    selectedIds.push(cb.value);
                }
            });

            const btnDeleteBulk = document.getElementById('btnDeleteBulk');
            const selectedTxCount = document.getElementById('selectedTxCount');
            const checkAllTx = document.getElementById('checkAllTx');

            if (selectedIds.length > 0) {
                if (selectedTxCount) selectedTxCount.innerText = selectedIds.length;
                if (btnDeleteBulk) {
                    btnDeleteBulk.style.display = 'inline-flex';
                }
            } else {
                if (btnDeleteBulk) {
                    btnDeleteBulk.style.display = 'none';
                }
            }

            // Sync master checkbox state
            if (checkAllTx) {
                checkAllTx.checked = (selectedIds.length === checkboxes.length && checkboxes.length > 0);
            }
        }

        function submitBulkDelete() {
            const checkboxes = document.querySelectorAll('.tx-checkbox');
            const selectedIds = [];
            checkboxes.forEach(cb => {
                if (cb.checked) {
                    selectedIds.push(cb.value);
                }
            });

            if (selectedIds.length === 0) {
                alert('Pilih setidaknya satu transaksi untuk dihapus.');
                return;
            }

            if (confirm(`Apakah Anda yakin ingin menghapus ${selectedIds.length} transaksi yang dipilih? Tindakan ini tidak dapat dibatalkan.`)) {
                const form = document.getElementById('bulkDeleteForm');
                const container = document.getElementById('bulkDeleteIdsContainer');
                
                // Clear old inputs
                container.innerHTML = '';
                
                // Append selected IDs as hidden inputs
                selectedIds.forEach(id => {
                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = 'ids[]';
                    input.value = id;
                    container.appendChild(input);
                });

                // Add activeTab to keep user on the same tab
                let tabInput = document.createElement('input');
                tabInput.type = 'hidden';
                tabInput.name = 'activeTab';
                
                const isSettlements = document.getElementById('nav-settlements').classList.contains('active');
                tabInput.value = isSettlements ? 'settlements' : 'transactions';
                
                container.appendChild(tabInput);

                form.submit();
            }
        }

        // Settlement Delete and Bulk Delete Handlers
        function confirmDeleteSettlement(id, transactionNumber) {
            if (confirm(`Apakah Anda yakin ingin menghapus advance/settlement ${transactionNumber}?`)) {
                const form = document.getElementById('deleteSettlementForm');
                form.action = `/settlements/${id}`;

                // Add activeTab to keep user on the same tab
                let tabInput = document.createElement('input');
                tabInput.type = 'hidden';
                tabInput.name = 'activeTab';
                tabInput.value = 'settlements';
                
                form.appendChild(tabInput);
                form.submit();
            }
        }

        function initiateEditSettlement(btn) {
            try {
                const adv = JSON.parse(btn.getAttribute('data-adv'));
                
                const form = document.getElementById('editSettlementForm');
                form.action = `/settlements/${adv.id}`;

                document.getElementById('edit_sett_date').value = adv.transaction_date;
                document.getElementById('edit_sett_recipient').value = adv.recipient_name;
                document.getElementById('edit_sett_amount').value = adv.amount;
                document.getElementById('edit_sett_payment_account').value = adv.payment_account_id;
                document.getElementById('edit_sett_description').value = adv.description;

                const detailsSection = document.getElementById('editSettlementDetailsSection');
                const settleAmountInput = document.getElementById('edit_settle_amount_input');
                const expenseAccountInput = document.getElementById('edit_sett_expense_account');

                if (adv.advance_status === 'settled') {
                    detailsSection.style.display = 'block';
                    settleAmountInput.value = adv.settlement_amount;
                    expenseAccountInput.value = adv.expense_account_id;
                    settleAmountInput.setAttribute('required', 'required');
                    expenseAccountInput.setAttribute('required', 'required');
                    calculateEditSettlementDiff();
                } else {
                    detailsSection.style.display = 'none';
                    settleAmountInput.value = '';
                    expenseAccountInput.value = '';
                    settleAmountInput.removeAttribute('required');
                    expenseAccountInput.removeAttribute('required');
                }

                document.getElementById('editSettlementModal').classList.add('active');
            } catch (e) {
                console.error("Gagal melakukan parse data settlement: ", e);
                alert("Terjadi kesalahan saat memuat data settlement.");
            }
        }

        function closeEditSettlementModal() {
            document.getElementById('editSettlementModal').classList.remove('active');
        }

        function calculateEditSettlementDiff() {
            const advAmountRaw = document.getElementById('edit_sett_amount').value;
            const settAmountRaw = document.getElementById('edit_settle_amount_input').value;

            const advAmount = parseFloat(advAmountRaw.replace(/\./g, '')) || 0;
            const settAmount = parseFloat(settAmountRaw.replace(/\./g, '')) || 0;

            const diffHelp = document.getElementById('editSettlementDiffHelp');
            if (!settAmount) {
                diffHelp.innerText = '';
                return;
            }

            const diff = settAmount - advAmount;
            if (diff > 0) {
                diffHelp.style.color = '#fca5a5'; // Light red
                diffHelp.innerText = `Kurang bayar: Perusahaan membayar sisa Rp ${new Intl.NumberFormat('id-ID').format(diff)} ke Karyawan.`;
            } else if (diff < 0) {
                diffHelp.style.color = '#86efac'; // Light green
                diffHelp.innerText = `Lebih bayar: Karyawan mengembalikan sisa Rp ${new Intl.NumberFormat('id-ID').format(Math.abs(diff))} ke Kas.`;
            } else {
                diffHelp.style.color = '#e2e8f0'; // Off-white
                diffHelp.innerText = 'Pas: Nominal Riil sama dengan Nominal Advance.';
            }
        }

        function toggleCheckAllSettlements(master) {
            const checkboxes = document.querySelectorAll('.settlement-checkbox');
            checkboxes.forEach(cb => {
                cb.checked = master.checked;
            });
            updateBulkDeleteSettlementsState();
        }

        function updateBulkDeleteSettlementsState() {
            const checkboxes = document.querySelectorAll('.settlement-checkbox');
            const selectedIds = [];
            checkboxes.forEach(cb => {
                if (cb.checked) {
                    selectedIds.push(cb.value);
                }
            });

            const btnDeleteBulkSettlements = document.getElementById('btnDeleteBulkSettlements');
            const selectedSettlementCount = document.getElementById('selectedSettlementCount');
            const checkAllSettlements = document.getElementById('checkAllSettlements');

            if (selectedIds.length > 0) {
                if (selectedSettlementCount) selectedSettlementCount.innerText = selectedIds.length;
                if (btnDeleteBulkSettlements) {
                    btnDeleteBulkSettlements.style.display = 'inline-flex';
                }
            } else {
                if (btnDeleteBulkSettlements) {
                    btnDeleteBulkSettlements.style.display = 'none';
                }
            }

            // Sync master checkbox state
            if (checkAllSettlements) {
                checkAllSettlements.checked = (selectedIds.length === checkboxes.length && checkboxes.length > 0);
            }
        }

        function submitBulkDeleteSettlements() {
            const checkboxes = document.querySelectorAll('.settlement-checkbox');
            const selectedIds = [];
            checkboxes.forEach(cb => {
                if (cb.checked) {
                    selectedIds.push(cb.value);
                }
            });

            if (selectedIds.length === 0) {
                alert('Pilih setidaknya satu settlement untuk dihapus.');
                return;
            }

            if (confirm(`Apakah Anda yakin ingin menghapus ${selectedIds.length} settlement/advance yang dipilih? Tindakan ini tidak dapat dibatalkan.`)) {
                const form = document.getElementById('bulkDeleteSettlementsForm');
                const container = document.getElementById('bulkDeleteSettlementsIdsContainer');
                
                // Clear old inputs
                container.innerHTML = '';
                
                // Append selected IDs as hidden inputs
                selectedIds.forEach(id => {
                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = 'ids[]';
                    input.value = id;
                    container.appendChild(input);
                });

                // Add activeTab
                let tabInput = document.createElement('input');
                tabInput.type = 'hidden';
                tabInput.name = 'activeTab';
                tabInput.value = 'settlements';
                container.appendChild(tabInput);

                form.submit();
            }
        }

        // ── Floating Overlay Sidebar Toggle ──
        const sidebarDrawer   = document.getElementById('sidebarDrawer');
        const sidebarBackdrop = document.getElementById('sidebarBackdrop');
        const menuPillBtn     = document.getElementById('menuPillBtn');
        const menuPillPlus    = document.getElementById('menuPillPlus');
        const menuPillText    = document.getElementById('menuPillText');

        function openSidebar() {
            // Apply dynamic stagger delays to visible items
            const navItems = sidebarDrawer.querySelectorAll('.sidebar-nav-item');
            navItems.forEach((item, index) => {
                item.style.transitionDelay = `${0.08 + (index * 0.06)}s`;
            });

            sidebarDrawer.classList.add('open');
            sidebarBackdrop.classList.add('active');
            menuPillBtn.classList.add('open');
            menuPillText.textContent = 'CLOSE';
            menuPillPlus.textContent = '×';
            document.body.style.overflow = 'hidden';
        }

        function closeSidebar() {
            sidebarDrawer.classList.remove('open');
            sidebarBackdrop.classList.remove('active');
            menuPillBtn.classList.remove('open');
            menuPillText.textContent = 'MENU';
            menuPillPlus.textContent = '+';
            document.body.style.overflow = '';

            // Reset transition delay so closing is immediate/clean
            const navItems = sidebarDrawer.querySelectorAll('.sidebar-nav-item');
            navItems.forEach(item => {
                item.style.transitionDelay = '';
            });
        }

        function toggleSidebar() {
            if (sidebarDrawer.classList.contains('open')) {
                closeSidebar();
            } else {
                openSidebar();
            }
        }

        // Modals global references
        const userModal = document.getElementById('userModal');
        const advanceModal = document.getElementById('advanceModal');
        const settleModal = document.getElementById('settleModal');

        // 1. User Modal Handlers
        function openUserModal() {
            if (!userModal) return;
            const title = document.getElementById('userModalTitle');
            const form = document.getElementById('userForm');
            const method = document.getElementById('userFormMethod');
            const submitBtn = document.getElementById('userSubmitBtn');
            const pwdLabel = document.getElementById('passwordLabel');
            const pwdHelp = document.getElementById('passwordHelp');
            const passwordInput = document.getElementById('usr_password');

            title.innerText = 'Tambah User Baru';
            form.action = "{{ route('web.users.store') }}";
            method.value = 'POST';
            submitBtn.innerText = 'Simpan User';
            
            document.getElementById('usr_name').value = '';
            document.getElementById('usr_email').value = '';
            passwordInput.value = '';
            passwordInput.setAttribute('required', 'required');
            pwdLabel.innerText = 'Kata Sandi';
            if (pwdHelp) pwdHelp.style.display = 'none';

            document.getElementById('usr_role').value = 'staff';
            toggleUserPermissionsSection();

            userModal.classList.add('active');
        }

        function closeUserModal() {
            if (userModal) userModal.classList.remove('active');
        }

        function editUser(btn) {
            if (!userModal) return;
            const usr = JSON.parse(btn.getAttribute('data-user'));
            const title = document.getElementById('userModalTitle');
            const form = document.getElementById('userForm');
            const method = document.getElementById('userFormMethod');
            const submitBtn = document.getElementById('userSubmitBtn');
            const pwdLabel = document.getElementById('passwordLabel');
            const pwdHelp = document.getElementById('passwordHelp');
            const passwordInput = document.getElementById('usr_password');

            title.innerText = 'Edit Pengguna';
            form.action = `/settings/users/${usr.id}`;
            method.value = 'PUT';
            submitBtn.innerText = 'Perbarui User';

            document.getElementById('usr_name').value = usr.name;
            document.getElementById('usr_email').value = usr.email;
            
            passwordInput.value = '';
            passwordInput.removeAttribute('required');
            pwdLabel.innerText = 'Ganti Kata Sandi (Opsional)';
            if (pwdHelp) pwdHelp.style.display = 'block';

            document.getElementById('usr_role').value = usr.role;
            toggleUserPermissionsSection();

            // Set checkboxes for permissions
            const checkboxes = document.querySelectorAll('.perm-checkbox');
            checkboxes.forEach(cb => {
                cb.checked = usr.permissions.includes(cb.value);
            });

            userModal.classList.add('active');
        }

        function toggleUserPermissionsSection() {
            const role = document.getElementById('usr_role').value;
            const section = document.getElementById('userPermissionsSection');
            if (section) {
                if (role === 'owner') {
                    section.style.display = 'none';
                } else {
                    section.style.display = 'block';
                }
            }
        }

        // 2. Advance Modal Handlers
        function openAdvanceModal() {
            if (advanceModal) advanceModal.classList.add('active');
        }

        function closeAdvanceModal() {
            if (advanceModal) advanceModal.classList.remove('active');
        }

        // 3. Settle Modal Handlers
        let currentSettleAdvAmount = 0;
        function openSettleModal(id, number, amount, recipientName) {
            if (!settleModal) return;
            const form = document.getElementById('settleAdvanceForm');
            form.action = `/settlements/${id}/settle`;

            document.getElementById('settle_adv_num').value = number;
            document.getElementById('settle_adv_recipient').value = recipientName || '-';
            
            // Format number to currency
            currentSettleAdvAmount = parseFloat(amount);
            document.getElementById('settle_adv_amount').value = `Rp ${new Intl.NumberFormat('id-ID').format(amount)}`;
            
            document.getElementById('settle_amount_input').value = '';
            document.getElementById('settlementDiffHelp').innerText = '';

            settleModal.classList.add('active');
        }

        function closeSettleModal() {
            if (settleModal) settleModal.classList.remove('active');
        }

        function calculateSettlementDiff() {
            const inputValRaw = document.getElementById('settle_amount_input').value.replace(/\./g, '');
            const helpEl = document.getElementById('settlementDiffHelp');
            if (!inputValRaw) {
                helpEl.innerText = '';
                return;
            }

            const settlementAmount = parseFloat(inputValRaw);
            const diff = settlementAmount - currentSettleAdvAmount;
            
            if (diff === 0) {
                helpEl.style.color = '#34d399'; // Green
                helpEl.innerText = 'Nilai pas. Jurnal penyesuaian seimbang.';
            } else if (diff < 0) {
                helpEl.style.color = '#60a5fa'; // Blue
                const returnedAmount = Math.abs(diff);
                helpEl.innerText = `Kelebihan Advance: Karyawan mengembalikan sisa Rp ${new Intl.NumberFormat('id-ID').format(returnedAmount)} ke kas.`;
            } else {
                helpEl.style.color = '#f87171'; // Red
                const companyPays = diff;
                helpEl.innerText = `Kekurangan Advance: Perusahaan membayarkan sisa Rp ${new Intl.NumberFormat('id-ID').format(companyPays)} ke karyawan.`;
            }
        }

        // ── Cash Advance JS Handlers ──
        function openLoanModal() {
            document.getElementById('loanModal').classList.add('active');
        }
        function closeLoanModal() {
            document.getElementById('loanModal').classList.remove('active');
        }
        function openRepaymentModal(id, loanNum, recipient, remaining) {
            const form = document.getElementById('repaymentForm');
            form.action = `/cash-advances/${id}/repay`;
            
            document.getElementById('repay_loan_num').value = loanNum;
            document.getElementById('repay_loan_recipient').value = recipient;
            document.getElementById('repay_loan_remaining').value = 'Rp ' + remaining;
            
            document.getElementById('repaymentModal').classList.add('active');
        }
        function closeRepaymentModal() {
            document.getElementById('repaymentModal').classList.remove('active');
        }
        function initiateEditLoan(btn) {
            try {
                const loan = JSON.parse(btn.getAttribute('data-loan'));
                const form = document.getElementById('editLoanForm');
                form.action = `/cash-advances/${loan.id}`;
                
                document.getElementById('edit_loan_date').value = loan.transaction_date;
                document.getElementById('edit_loan_recipient').value = loan.recipient_name;
                document.getElementById('edit_loan_amount').value = loan.amount;
                document.getElementById('edit_loan_payment_account').value = loan.payment_account_id;
                document.getElementById('edit_loan_description').value = loan.description;
                
                // Populate is_transferred
                const isTransferredCheckbox = document.getElementById('edit_loan_is_transferred');
                if (isTransferredCheckbox) {
                    isTransferredCheckbox.checked = (loan.is_transferred === true || loan.is_transferred === 1 || loan.is_transferred === "1");
                }

                // Populate transferred_amount
                const transferredAmountInput = document.getElementById('edit_loan_transferred_amount');
                if (transferredAmountInput) {
                    transferredAmountInput.value = loan.transferred_amount || '';
                }

                toggleEditLoanTransferFields();

                document.getElementById('editLoanModal').classList.add('active');
            } catch (e) {
                console.error(e);
            }
        }

        // Toggles visibility of edit loan transfer details
        function toggleEditLoanTransferFields() {
            const isTransferredCheckbox = document.getElementById('edit_loan_is_transferred');
            const detailsSection = document.getElementById('editLoanTransferDetailsSection');
            const transferredAmountInput = document.getElementById('edit_loan_transferred_amount');

            if (isTransferredCheckbox && isTransferredCheckbox.checked) {
                if (detailsSection) detailsSection.style.display = 'block';
            } else {
                if (detailsSection) detailsSection.style.display = 'none';
                if (transferredAmountInput) transferredAmountInput.value = '';
            }
        }
        function closeEditLoanModal() {
            document.getElementById('editLoanModal').classList.remove('active');
        }
        function confirmDeleteLoan(id, txNum) {
            if (confirm(`Apakah Anda yakin ingin menghapus pinjaman ${txNum} beserta seluruh riwayat angsurannya?`)) {
                const form = document.getElementById('deleteLoanForm');
                form.action = `/cash-advances/${id}`;
                form.submit();
            }
        }
        function confirmDeleteRepayment(id, txNum) {
            if (confirm(`Apakah Anda yakin ingin menghapus angsuran ${txNum}? Saldo pinjaman induk akan dikembalikan.`)) {
                const form = document.getElementById('deleteRepaymentForm');
                form.action = `/cash-advances/repay/${id}`;
                form.submit();
            }
        }
        function toggleRepaymentsRow(id, btn) {
            const row = document.getElementById('repayments-row-' + id);
            const icon = document.getElementById('toggle-icon-' + id);
            if (row.style.display === 'none') {
                row.style.display = 'table-row';
                icon.style.transform = 'rotate(90deg)';
            } else {
                row.style.display = 'none';
                icon.style.transform = 'rotate(0deg)';
            }
        }

        // Close on Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') closeSidebar();
        });

        // Close modal when clicking outside card
        window.onclick = function(event) {
            if (event.target === transactionModal) {
                closeTransactionModal();
            }
            if (event.target === receiptModal) {
                closeReceiptModal();
            }
            if (event.target === userModal) {
                closeUserModal();
            }
            if (event.target === advanceModal) {
                closeAdvanceModal();
            }
            if (event.target === settleModal) {
                closeSettleModal();
            }
            if (event.target === document.getElementById('editSettlementModal')) {
                closeEditSettlementModal();
            }
            if (event.target === document.getElementById('loanModal')) {
                closeLoanModal();
            }
            if (event.target === document.getElementById('repaymentModal')) {
                closeRepaymentModal();
            }
            if (event.target === document.getElementById('editLoanModal')) {
                closeEditLoanModal();
            }
        }
    </script>
</body>
</html>
