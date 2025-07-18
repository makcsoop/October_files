<?php namespace Dashboard\Widgets\Dash;

use BackendAuth;
use Dashboard\Models\Dashboard as DashboardModel;

/**
 * ReportProcessor concern
 */
trait ReportProcessor
{
    /**
     * processPermissionCheck check if user has permissions to show the report
     * and removes it if permission is denied
     */
    protected function processPermissionCheck(array $reports)
    {
        foreach ($reports as $reportName => $report) {
            if (
                $report->permissions &&
                !BackendAuth::userHasAccess($report->permissions, false)
            ) {
                $this->removeReport($reportName);
            }
        }
    }

    /**
     * processDashWidgetReports will mutate reports types that are registered as widgets,
     * convert their type to 'widget' and internally allocate the widget object
     */
    protected function processDashWidgetReports(array $reports)
    {
        foreach ($reports as $report) {
            if (!$this->isReportWidget((string) $report->type)) {
                continue;
            }

            $newConfig = ['widget' => $report->type];

            if (is_array($report->config)) {
                $newConfig += $report->config;
            }

            $widgetType = $this->isVueReportWidget($report->type)
                ? 'widget'
                : 'static';

            $report->useConfig($newConfig)->displayAs($widgetType);

            // Create form widget instance and bind to controller
            $this->makeDashReportWidget($report)->bindToController();
        }
    }

    /**
     * processReportRows
     */
    protected function processReportRows(array $reports)
    {
        // Already loaded from saved dash
        if ($this->allRows) {
            return;
        }

        $rows = [];

        foreach ($reports as $report) {
            $extraConfig = [
                'metrics' => $this->processReportRowWidgetMetrics((array) $report->metrics),
                'widgetClass' => $report->widget,
            ];

            if ($report->type === 'widget') {
                $extraConfig['componentName'] = strtolower(str_replace('\\', '-', $report->widget));
            }

            $report->configuration($extraConfig + $report->config);

            $rows[$report->row]['widgets'][] = $report;
        }

        $this->allRows = array_values($rows);
    }

    /**
     * processSavedDashRows
     */
    protected function processSavedDashRows(array $reports)
    {
        // @todo check if dash can be customized

        $savedDash = DashboardModel::fetchDashboard(
            $this->controller,
            $this->code
        );

        if ($savedDash && $savedDash->definition) {
            $this->allRows = $savedDash->definition;
        }
    }

    /**
     * processReportRowWidgetMetrics
     */
    protected function processReportRowWidgetMetrics($metrics)
    {
        $result = [];

        foreach ($metrics as $name => $config) {
            $result[] = ['metric' => $name] + $config;
        }

        return $result;
    }
}
