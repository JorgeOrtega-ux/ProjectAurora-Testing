<?php
// includes/sections/system/error-missing-data.php
?>
<style>
    /* Estilos específicos para la sección Missing Data */
    .missing-data-container {
        text-align: center;
    }

    .missing-data-title {
        font-size: 32px;
        margin-bottom: 25px;
        color: #000; /* Aseguramos color por defecto */
    }

    .missing-data-box {
        border: 1px solid #e0e0e0;
        border-radius: 8px;
        padding: 20px;
        text-align: left;
        background-color: #fff;
        box-shadow: 0 2px 4px rgba(0,0,0,0.02); /* Pequeño detalle extra para pulirlo */
    }

    .missing-data-error-title {
        margin: 0 0 10px 0;
        font-size: 16px;
        color: #000;
        font-weight: 600;
    }

    .missing-data-text {
        margin: 0;
        font-size: 14px;
        color: #666;
        line-height: 1.5;
    }
</style>

<div class="section-content active" data-section="error-missing-data">
    <div class="section-center-wrapper">
        <div class="form-container missing-data-container">
            
            <h1 class="missing-data-title" data-i18n="system.missing_data_title">
                <?php echo translation('system.missing_data_title'); ?>
            </h1>
            
            <div class="missing-data-box">
                <h3 class="missing-data-error-title" data-i18n="system.missing_data_error">
                    <?php echo translation('system.missing_data_error'); ?>
                </h3>
                
                <p class="missing-data-text">
                    <?php 
                    echo isset($missingDataMessage) 
                        ? $missingDataMessage 
                        : '<span data-i18n="system.missing_data_default">' . translation('system.missing_data_default') . '</span>'; 
                    ?>
                </p>
            </div>

        </div>
    </div>
</div>