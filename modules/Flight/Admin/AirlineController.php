<?php


    namespace Modules\Flight\Admin;


    use Illuminate\Http\Request;
    use Illuminate\Support\Facades\Auth;
    use Illuminate\Support\Facades\Validator;
    use Illuminate\Validation\Rule;
    use Modules\AdminController;
    use Modules\Flight\Models\Flight;
    use Modules\Flight\Models\Airline;

    class AirlineController extends AdminController
    {
        /**
         * @var string
         */
        private $airline;

        /**
         * @var string
         */

        public function __construct()
        {
            $this->setActiveMenu(route('flight.admin.index'));
            $this->airline = Airline::class;
        }

        public function callAction($method, $parameters)
        {
            if(!Flight::isEnable())
            {
                return redirect('/');
            }
            return parent::callAction($method, $parameters); // TODO: Change the autogenerated stub
        }

        public function index(Request $request)
        {
            $this->checkPermission('flight_view');
            $query = $this->airline::query() ;
            $query->orderBy('id', 'desc');

            if (!empty($flight_name = $request->input('s'))) {
                $query->where('name', 'LIKE', '%' . $flight_name . '%');
            }
            if ($this->hasPermission('flight_manage_others')) {
                if (!empty($author = $request->input('vendor_id'))) {
                    $query->where('author_id', $author);
                }
            } else {
                $query->where('author_id', Auth::id());
            }
            $data = [
                'rows'               => $query->with(['author'])->paginate(20),
                'flight_manage_others' => $this->hasPermission('flight_manage_others'),
                'breadcrumbs'        => [
                    [
                        'name' => __('Airline'),
                        'url'  => route('flight.admin.airline.index')
                    ],
                    [
                        'name'  => __('All'),
                        'class' => 'active'
                    ],
                ],
                'page_title'=>__("Airline Management")
            ];
            return view('Flight::admin.airline.index', $data);
        }
        public function edit(Request $request, $id)
        {
            $this->checkPermission('flight_update');
            $row = $this->airline::find($id);
            if (empty($row)) {
                return redirect(route('flight.admin.airline.index'));
            }
            if (!$this->hasPermission('flight_manage_others')) {
                if ($row->author_id != Auth::id()) {
                    return redirect(route('flight.admin.index'));
                }
            }
            $data = [
                'row'            => $row,
                'breadcrumbs'    => [
                    [
                        'name' => __('Airline'),
                        'url'  => route('flight.admin.airline.index')
                    ],
                    [
                        'name'  => __('Edit airline'),
                        'class' => 'active'
                    ],
                ],
                'page_title'=>__("Edit: :name",['name'=>$row->code])
            ];
            return view('Flight::admin.airline.detail', $data);
        }

        public function store( Request $request, $id ){

            if($id>0){
                $this->checkPermission('flight_update');
                $row = $this->airline::find($id);
                if (empty($row)) {
                    return redirect(route('flight.admin.airline.index'));
                }

                if($row->author_id != Auth::id() and !$this->hasPermission('flight_manage_others'))
                {
                    return redirect(route('flight.admin.airline.index'));
                }
            }else{
                $this->checkPermission('flight_create');
                $row = new $this->airline();
            }
            $validator = Validator::make($request->all(), [
                'name'=>'required',
                'image_id'=>'required',
            ]);
            if ($validator->fails()) {
                return redirect()->back()->with(['errors' => $validator->errors()]);
            }
            $dataKeys = [
                'name',
                'image_id'
            ];
            if($this->hasPermission('flight_manage_others')){
                $dataKeys[] = 'author_id';
            }
            $row->fillByAttr($dataKeys,$request->input());
            $res = $row->save();
            if ($res) {
                return redirect(route('flight.admin.airline.edit',$row))->with('success', __('Airline saved') );
            }
        }


        public function bulkEdit(Request $request)
        {

            $ids = $request->input('ids');
            $action = $request->input('action');
            if (empty($ids) or !is_array($ids)) {
                return redirect()->back()->with('error', __('No items selected!'));
            }
            if (empty($action)) {
                return redirect()->back()->with('error', __('Please select an action!'));
            }

            switch ($action){
                case "delete":
                    foreach ($ids as $id) {
                        $query = $this->airline::where("id", $id);
                        if (!$this->hasPermission('flight_manage_others')) {
                            $query->where("create_user", Auth::id());
                            $this->checkPermission('flight_delete');
                        }
                        $row  =  $query->first();
                        if(!empty($row)){
                            $row->delete();
                        }
                    }
                    return redirect()->back()->with('success', __('Deleted success!'));
                    break;
                case "permanently_delete":
                    foreach ($ids as $id) {
                        $query = $this->airline::where("id", $id);
                        if (!$this->hasPermission('flight_manage_others')) {
                            $query->where("create_user", Auth::id());
                            $this->checkPermission('flight_delete');
                        }
                        $row  =  $query->first();
                        if($row){
                            $row->delete();
                        }
                    }
                    return redirect()->back()->with('success', __('Permanently delete success!'));
                    break;
                case "clone":
                    $this->checkPermission('flight_create');
                    foreach ($ids as $id) {
                        (new $this->airline())->saveCloneByID($id);
                    }
                    return redirect()->back()->with('success', __('Clone success!'));
                    break;
                default:
                    // Change status
                    foreach ($ids as $id) {
                        $query = $this->airline::where("id", $id);
                        if (!$this->hasPermission('flight_manage_others')) {
                            $query->where("create_user", Auth::id());
                            $this->checkPermission('flight_update');
                        }
                        $row = $query->first();
                        $row->status  = $action;
                        $row->save();
                    }
                    return redirect()->back()->with('success', __('Update success!'));
                    break;
            }


        }
        public function getForSelect2(Request $request)
        {
            $pre_selected = $request->query('pre_selected');
            $selected = $request->query('selected');

            if($pre_selected && $selected){
                $items = $this->airline::find($selected);
                return [
                    'results'=>$items
                ];
            }
            $q = $request->query('q');
            $query = $this->airline::select('id', 'name as text');
            if ($q) {
                $query->where('name', 'like', '%' . $q . '%');
            }
            $res = $query->orderBy('id', 'desc')->limit(20)->get();
            return response()->json([
                'results' => $res
            ]);
        }

    }
