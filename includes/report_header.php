<?php
/**
 * Universal Report Print & PDF Header Component
 * Bestway Wholesale Distribution
 *
 * Variables accepted:
 * - $report_title (e.g. 'Sales & Revenue Report')
 * - $report_subtitle (e.g. 'Customer: Al-Shafi Pharmacy')
 * - $report_period (e.g. '01 Sep 2026 to 21 Sep 2026')
 * - $report_filename (e.g. 'sales_report')
 * - $report_orientation (e.g. 'landscape' or 'portrait')
 */

if (!isset($company_info)) {
    $company_info = [
        'business_name'   => 'Bestway Distribution',
        'tagline'         => 'Wholesale Medicine & Pharma Distribution',
        'logo_path'       => 'assets/images/logo.png',
        'phone'           => '0300-1234567 / 0321-7654321',
        'address'         => 'Flate #01 Majid Haleema Sadia road Gate #01 Al Rehman Garden Ph 2 Sharaqpur Road Sheikhupura'
    ];
    if (isset($pdo) && $pdo) {
        try {
            $cs_row = $pdo->query("SELECT * FROM company_settings LIMIT 1")->fetch(PDO::FETCH_ASSOC);
            if ($cs_row) {
                if (!empty($cs_row['business_name']))   $company_info['business_name']   = $cs_row['business_name'];
                if (!empty($cs_row['tagline']))         $company_info['tagline']         = $cs_row['tagline'];
                if (!empty($cs_row['logo_path']))       $company_info['logo_path']       = $cs_row['logo_path'];
                if (!empty($cs_row['phone']))           $company_info['phone']           = $cs_row['phone'];
                if (!empty($cs_row['address']))         $company_info['address']         = $cs_row['address'];
                
            }
        } catch (Exception $e) {}
    }
}

$r_title       = $report_title ?? ($page_title ?? 'System Report');
$r_subtitle    = $report_subtitle ?? '';
$r_period      = $report_period ?? '';
$r_filename    = $report_filename ?? 'report_' . date('Ymd');
$r_orientation = $report_orientation ?? 'portrait';
?>

<!-- Printable & PDF Header -->
<div class="report-print-header d-none d-print-block mb-3">
    <div class="row align-items-center mb-2">
        <!-- Left: Logo & Company Name -->
        <div class="col-7 text-start">
            <div class="d-flex align-items-center gap-3">
                <img src="<?php echo BASE_URL; ?>assets/images/logo.png" alt="Company Logo" class="company-logo" style="max-height: 60px; max-width: 170px; object-fit: contain;">
                <div>
                    <h3 class="company-name mb-0 text-dark fw-bold" style="font-size: 20px; text-transform: uppercase; letter-spacing: -0.5px;">
                        <?php echo htmlspecialchars($company_info['business_name']); ?>
                    </h3>
                    <div class="company-tagline text-muted small" style="font-size: 11px;">
                        <?php echo htmlspecialchars($company_info['tagline']); ?>
                    </div>
                </div>
            </div>
        </div>
        <!-- Right: Address & Phone -->
        <div class="col-5 text-end company-address" style="font-size: 11.5px; line-height: 1.35;">
            <div class="fw-semibold text-dark mb-1">
                <i class="fa-solid fa-location-dot text-secondary me-1"></i>
                <?php echo htmlspecialchars($company_info['address']); ?>
            </div>
            <div class="text-secondary small">
                <i class="fa-solid fa-phone text-secondary me-1"></i>
                <?php echo htmlspecialchars($company_info['phone']); ?>
                
            </div>
        </div>
    </div>
    
    <!-- Report Title Strip -->
    <div class="d-flex justify-content-between align-items-center pt-2 mt-1 border-top border-2 border-dark">
        <div>
            <span class="badge bg-primary text-white text-uppercase px-2 py-1" style="font-size: 11px; letter-spacing: 0.5px;">
                <?php echo htmlspecialchars($r_title); ?>
            </span>
            <?php if (!empty($r_subtitle)): ?>
                <span class="fw-bold text-dark ms-2 small"><?php echo htmlspecialchars($r_subtitle); ?></span>
            <?php endif; ?>
        </div>
        <div class="text-secondary small text-end" style="font-size: 11px;">
            <?php if (!empty($r_period)): ?>
                Period: <strong><?php echo $r_period; ?></strong> | 
            <?php endif; ?>
            Date: <strong><?php echo date('d-m-Y'); ?></strong>
        </div>
    </div>
</div>
