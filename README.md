datatable
=========

Kohana datatable jquery plugin implementation compatible with datatable 1.10 and later

Can join tables , order, sort ,search and etc
Using Example place youe code in controller or action , where u want response datatable as json
    				// payments is table name 
	                $dataTable = DataTable::instance('payments');
	                // array of columns , field table need for joined columns
	                $columns = array(
	                    array
	                    (
	                        'data' => 'id',
	                    ),
	                    array(
	                        'data' => 'user_id',
	                    ),
	                    array
	                    (
	                        'data' => 'amount',
	                    ),
	                    array
	                    (
	                        'data' => 'profit',
	                    ),
	                    array
	                    (
	                        'data' => 'title',
	                        'table'=> 'currencies'
	                    ),
	                    array
	                    (
	                        'data' => 'paysystem',
	                    ),
	                    array
	                    (
	                        'data' => 'merchant',
	                    ),
	                    array
	                    (
	                        'data' => 'time',
	                    ),
	                    array
	                    (
	                        'data' => 'time_status',
	                    ),
	                    array
	                    (
	                        'data' => 'status',
	                    ),
	                    array
	                    (
	                        'data' => 'details',
	                    ),
	                    array
	                    (
	                        'data' => 'comment',
	                    )
	                );
	                // set columns
	                $dataTable->setColumns($columns);
	                // set additional conditions
	                $dataTable->setCondition('payments.direction','=','withdraw');
	                $dataTable->setCondition('payments.status','=',Pay::STATUS_PENDING);
	                // set joinable table
	                $dataTable->setJoin('currencies', array('payments'=>'currency_id', 'currencies'=>'id'), $join = 'LEFT JOIN');
	                // finally response Json encoded string
	                $dataTable->sendData($this->request->post());
                
HTML Table
=========	
                        <table id="users_active_table1" class="table table-striped table-hover" width="100%">
                            <thead>
                            <tr>
                                <th>id</th>
                                <th>user_id</th>
                                <th>summ</th>
                                <th>payout</th>
                                <th>currency</th>
                                <th>service</th>
                                <th>merchant</th>
                                <th>time</th>
                                <th>time status</th>
                                <th>state</th>
                                <th>details</th>
                                <th>comment</th>
                            </tr>
                            </thead>
                            <tbody>

                            </tbody>
                        </table>	
                        
Js params on page
=========	                        
                    var table = $('#users_active_table1').dataTable({
			            serverSide: true,
			            "deferRender": true,
			            "autoWidth": false,
			            "ajax": {
			                "url": "ROUTE TO CONTROLLER ACTION",
			                "type": "POST"
			            },
			            "columns": [
			                {
			                    "data": "id"
			                },
			                {
			                    "data": "user_id"
			                },
			                {
			                    "data": "amount"
			                },
			                {
			                    "data": "profit"
			                },
			                {
			                    "data": "title"
			                },
			                {
			                    "data": "paysystem"
			                },
			                {
			                    "data": "merchant"
			                },
			                {
			                    "data": "time"
			                },
			                {
			                    "data": "time_status"
			                },
			                {
			                    "data": "status",
			                    render: function ( data, type, row ) {
			                        return data;
			                    }
			                },
			                {
			                    "data"      : "details",
			                    "orderable": false
			                },
			                {
			                    "data": "comment",
			                    "orderable": false
			                }
			            ],
			            "rowCallback": function( row, data, displayIndex ) {
			                $(row).addClass('cursor-pointer');
			            }
			        });
			
