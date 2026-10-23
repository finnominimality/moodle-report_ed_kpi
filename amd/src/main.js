define(['jquery',
    'report_ed_kpi/datatables',
    'report_ed_kpi/datatables-buttons',
    'report_ed_kpi/jszip',
    'report_ed_kpi/buttons.html5',
    'core/chartjs',
    // 'report_ed_kpi/bootstrap-select'
], function ($) {
    return {
        init_filters: function(){

            $('#departments').on('change', function(){
                $('#report-filter-form').submit();
            })

        },
        init_index: function (lang, tablecsvfilename) {

            var langs = {
                'fr': 'French',
                'en': 'English'
            };
            var lang_url = 'https://cdn.datatables.net/plug-ins/1.10.19/i18n/' + langs[lang] + '.json';

            $('#table-all-learnings').dataTable({
                "order": [[0, 'asc']],
                "pageLength": 50,
                "lengthMenu": [[5, 10, 25, 50, -1], [5, 10, 25, 50, 'All']],
                "dom": 'Blfrtip',
                "url": lang_url,
                "stateSave": true,
                "aoColumnDefs": [
                    {
                        bSortable: false,
                        aTargets: ['noSort']
                    },
                    {
                        bSearchable: false,
                        aTargets: ['noSearch']
                    }
                ],
                "buttons": [
                    {
                        extend: 'csvHtml5',
                        charset: 'utf-8',
                        fieldBoundary: '',
                        fieldSeparator: ';',
                        bom: true,
                        exportOptions: {
                            columns: [0, 1, 2, 3, 4, 5],
                        },
                        stripNewlines: false,
                        text: 'Export CSV',
                        className: 'btn btn-secondary csvexport',
                        filename: tablecsvfilename
                    }
                ],
            });
        },
        init_details: function (lang, tablecsvfilename) {

            var langs = {
                'fr': 'French',
                'en': 'English'
            };
            var lang_url = 'https://cdn.datatables.net/plug-ins/1.10.19/i18n/' + langs[lang] + '.json';

            $('#table-learning-details').dataTable({
                "order": [[0, 'desc']],
                "pageLength": 50,
                "lengthMenu": [[5, 10, 25, 50, -1], [5, 10, 25, 50, 'All']],
                "dom": 'Blfrtip',
                "url": lang_url,
                "stateSave": true,
                "aoColumnDefs": [
                    {
                        bSortable: false,
                        aTargets: ['noSort']
                    },
                    {
                        bSearchable: false,
                        aTargets: ['noSearch']
                    }
                ],
                "buttons": [
                    {
                        extend: 'csvHtml5',
                        charset: 'utf-8',
                        fieldBoundary: '',
                        fieldSeparator: ';',
                        bom: true,
                        exportOptions: {
                            columns: [0, 1, 2, 3, 4, 5, 6, 7],
                        },
                        stripNewlines: false,
                        text: 'Export CSV',
                        className: 'btn btn-secondary csvexport',
                        filename: tablecsvfilename
                    }
                ],
            });
        },

        init_circle_chart: function (id, data) {
            var ctx = document.getElementById(id).getContext('2d');

            const myChart = new Chart(ctx, {
                type: 'doughnut',
                data: data,
                options: {
                    // cutout: 140,
                    maintainAspectRatio: false,
                    responsive: true,
                    plugins: {
                        legend: {
                            position: 'right'
                        }
                    }
                }
            });
        },

        init_bar_chart: function (id, data) {
            var ctx = document.getElementById(id).getContext('2d');

            const myChart = new Chart(ctx, {
                type: 'bar',
                data: data,
                options: {
                    maintainAspectRatio: false,
                    responsive: true,
                    scales: {
                        x: {
                            stacked: true
                        },
                        y: {
                            stacked: true
                        }
                    },
                    plugins: {

                        tooltip: {
                            callbacks: {
                                label: function (context) {
                                    let label = context.dataset.label || '';

                                    if (label) {
                                        label += ': ';
                                    }

                                    label += Math.round(context.dataset.data[context.dataIndex]) + "% (" + context.dataset.realvalues[context.dataIndex] + ')';

                                    return label;
                                }
                            }
                        }
                    }
                }
            });


        }
    }
});
