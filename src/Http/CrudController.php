<?php

namespace Microcrud\Http;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Microcrud\Abstracts\Exceptions\CreateException;
use Microcrud\Abstracts\Exceptions\NotFoundException;
use Microcrud\Abstracts\Exceptions\UpdateException;
use Microcrud\Abstracts\Exceptions\ValidationException;
use Microcrud\Abstracts\Http\ApiBaseController;
use Microcrud\Interfaces\CrudBaseController;

/**
 * CrudController
 *
 * Abstract CRUD controller with built-in transaction management and error handling.
 * Provides standard REST API operations: index, show, create, update, delete, restore, bulkAction.
 *
 * @package Microcrud\Http
 */
abstract class CrudController extends ApiBaseController implements CrudBaseController
{
    /**
     * Constructor.
     *
     * @param string $model Model class name
     * @param string|null $service Service class name
     * @param string|null $resource Resource class name
     */
    public function __construct($model, $service = null, $resource = null)
    {
        parent::__construct($model, $service, $resource);
    }

    /**
     * Display a listing of the resource.
     *
     * Supports filtering, pagination, soft delete handling, and incremental sync.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        try {
            // Validate request data
            $data = method_exists($request, 'validated')
                ? $request->validated()
                : $this->service->globalValidation($request->all(), $this->service->indexRules());

            // Build base query with filters
            $itemsQuery = $this->service
                ->setData($data)
                ->beforeIndex()
                ->getQuery();

            // Handle "get all" without pagination
            if (!empty($data['is_all'])) {
                $this->service->setIsPaginated(false);
            }

            // Handle soft delete status filtering
            if (!empty($data['trashed_status']) && $this->service->is_soft_delete()) {
                switch ($data['trashed_status']) {
                    case -1: // Only trashed
                        $itemsQuery = $itemsQuery->onlyTrashed();
                        break;
                    case 1: // With trashed
                        $itemsQuery = $itemsQuery->withTrashed();
                        break;
                    default: // Active only (default behavior)
                        break;
                }
            }

            // Incremental sync support - get items updated after timestamp
            if (!empty($data['updated_at'])) {
                $itemsQuery = $itemsQuery->where('updated_at', '>=', $data['updated_at']);
            }

            $this->service->setQuery($itemsQuery);
        } catch (ValidationException $th) {
            return $this->error($th->getMessage(), 422, $th);
        } catch (\Exception $th) {
            return $this->errorBadRequest($th->getMessage(), $th);
        }

        // Return paginated or full collection
        return $this->service->getIsPaginated()
            ? $this->paginateQuery()
            : $this->getResource();
    }

    /**
     * Display the specified resource.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(Request $request)
    {
        try {
            // Validate request data
            $data = method_exists($request, 'validated')
                ? $request->validated()
                : $this->service->globalValidation($request->all(), $this->service->showRules());

            // Fetch item by ID with hooks
            $item = $this->service
                ->setData($data)
                ->beforeShow()
                ->setById()
                ->afterShow()
                ->get();
        } catch (ValidationException $th) {
            return $this->error($th->getMessage(), 422, $th);
        } catch (NotFoundException $th) {
            return $this->errorNotFound($th->getMessage(), $th);
        } catch (\Exception $th) {
            return $this->errorBadRequest($th->getMessage(), $th);
        }

        return $this->singleItem($this->service->getItemResource(), $item);
    }

    /**
     * Store a newly created resource in storage.
     *
     * Supports background job processing via 'is_job' parameter.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function create(Request $request)
    {
        $this->service->setIsTransactionEnabled(false);
        $useTransaction = !$this->service->getIsTransactionEnabled();

        if ($useTransaction) {
            DB::beginTransaction();
        }

        try {
            // Validate request data
            $data = method_exists($request, 'validated')
                ? $request->validated()
                : $this->service->globalValidation($request->all(), $this->service->createRules());

            // Handle background job processing
            if (!empty($data['is_job'])) {
                $this->service
                    ->setData($data)
                    ->createJob();

                if ($useTransaction) {
                    DB::commit();
                }

                return $this->success();
            }

            // Synchronous creation
            $item = $this->service
                ->setData($data)
                ->create()
                ->get();

            if ($useTransaction) {
                DB::commit();
            }

            return $this->created($this->service->getItemResource(), $item);
        } catch (ValidationException $th) {
            if ($useTransaction) {
                DB::rollBack();
            }
            return $this->error($th->getMessage(), 422, $th);
        } catch (CreateException $th) {
            if ($useTransaction) {
                DB::rollBack();
            }
            return $this->errorBadRequest($th->getMessage(), $th);
        } catch (\Exception $th) {
            if ($useTransaction) {
                DB::rollBack();
            }
            return $this->errorBadRequest($th->getMessage(), $th);
        }
    }

    /**
     * Update the specified resource in storage.
     *
     * Supports background job processing via 'is_job' parameter.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request)
    {
        $this->service->setIsTransactionEnabled(false);
        $useTransaction = !$this->service->getIsTransactionEnabled();

        if ($useTransaction) {
            DB::beginTransaction();
        }

        try {
            // Validate request data
            $data = method_exists($request, 'validated')
                ? $request->validated()
                : $this->service->globalValidation($request->all(), $this->service->updateRules());

            // Handle background job processing
            if (!empty($data['is_job'])) {
                $this->service
                    ->setData($data)
                    ->setById()
                    ->updateJob();

                if ($useTransaction) {
                    DB::commit();
                }

                return $this->success();
            }

            // Synchronous update
            $item = $this->service
                ->setData($data)
                ->setById()
                ->update()
                ->get();

            if ($useTransaction) {
                DB::commit();
            }

            return $this->accepted($this->service->getItemResource(), $item);
        } catch (ValidationException $th) {
            if ($useTransaction) {
                DB::rollBack();
            }
            return $this->error($th->getMessage(), 422, $th);
        } catch (UpdateException $th) {
            if ($useTransaction) {
                DB::rollBack();
            }
            return $this->errorBadRequest($th->getMessage(), $th);
        } catch (NotFoundException $th) {
            if ($useTransaction) {
                DB::rollBack();
            }
            return $this->errorNotFound($th->getMessage(), $th);
        } catch (\Exception $th) {
            if ($useTransaction) {
                DB::rollBack();
            }
            return $this->errorBadRequest($th->getMessage(), $th);
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * Supports both soft delete and force delete (permanent removal).
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function delete(Request $request)
    {
        $this->service->setIsTransactionEnabled(false);
        $useTransaction = !$this->service->getIsTransactionEnabled();

        if ($useTransaction) {
            DB::beginTransaction();
        }

        try {
            // Validate request data
            $data = method_exists($request, 'validated')
                ? $request->validated()
                : $this->service->globalValidation($request->all(), $this->service->deleteRules());

            // Handle force delete (permanent removal of soft-deleted items)
            if (!empty($request->is_force_destroy)) {
                $this->service->setQuery($this->service->getQuery()->withTrashed());
            }

            // Delete the item
            $this->service
                ->setData($data)
                ->setById()
                ->delete();

            if ($useTransaction) {
                DB::commit();
            }

            return $this->noContent();
        } catch (ValidationException $th) {
            if ($useTransaction) {
                DB::rollBack();
            }
            return $this->error($th->getMessage(), 422, $th);
        } catch (NotFoundException $th) {
            if ($useTransaction) {
                DB::rollBack();
            }
            return $this->errorNotFound($th->getMessage(), $th);
        } catch (\Exception $th) {
            if ($useTransaction) {
                DB::rollBack();
            }
            return $this->errorBadRequest($th->getMessage(), $th);
        }
    }

    /**
     * Restore a soft-deleted resource.
     *
     * Requires soft delete to be enabled on the model.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function restore(Request $request)
    {
        $this->service->setIsTransactionEnabled(false);
        $useTransaction = !$this->service->getIsTransactionEnabled();

        if ($useTransaction) {
            DB::beginTransaction();
        }

        try {
            // Validate request data
            $data = method_exists($request, 'validated')
                ? $request->validated()
                : $this->service->globalValidation($request->all(), $this->service->restoreRules());

            // Restore the soft-deleted item
            $item = $this->service
                ->setQuery($this->service->getQuery()->withTrashed())
                ->setData($data)
                ->setById()
                ->restore()
                ->get();

            if ($useTransaction) {
                DB::commit();
            }

            return $this->accepted($this->service->getItemResource(), $item);
        } catch (ValidationException $th) {
            if ($useTransaction) {
                DB::rollBack();
            }
            return $this->error($th->getMessage(), 422, $th);
        } catch (NotFoundException $th) {
            if ($useTransaction) {
                DB::rollBack();
            }
            return $this->errorNotFound($th->getMessage(), $th);
        } catch (CreateException $th) {
            if ($useTransaction) {
                DB::rollBack();
            }
            return $this->errorBadRequest($th->getMessage(), $th);
        } catch (UpdateException $th) {
            if ($useTransaction) {
                DB::rollBack();
            }
            return $this->errorBadRequest($th->getMessage(), $th);
        } catch (\Exception $th) {
            if ($useTransaction) {
                DB::rollBack();
            }
            return $this->errorBadRequest($th->getMessage(), $th);
        }
    }

    /**
     * Perform bulk actions on multiple resources.
     *
     * Supports bulk create, update, delete, and restore operations.
     * Can be processed asynchronously via background job with 'is_job' parameter.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function bulkAction(Request $request)
    {
        $this->service->setIsTransactionEnabled(false);
        $useTransaction = !$this->service->getIsTransactionEnabled();

        if ($useTransaction) {
            DB::beginTransaction();
        }

        try {
            // Validate request data
            $data = method_exists($request, 'validated')
                ? $request->validated()
                : $this->service->globalValidation($request->all(), $this->service->bulkActionRules());

            // Handle background job processing
            if (!empty($data['is_job'])) {
                $this->service
                    ->setData($data)
                    ->bulkActionJob();

                if ($useTransaction) {
                    DB::commit();
                }

                return $this->success();
            }

            // Synchronous bulk action processing
            $this->service
                ->setData($data)
                ->bulkAction()
                ->get();

            if ($useTransaction) {
                DB::commit();
            }

            return $this->getResource();
        } catch (ValidationException $th) {
            if ($useTransaction) {
                DB::rollBack();
            }
            return $this->error($th->getMessage(), 422, $th);
        } catch (NotFoundException $th) {
            if ($useTransaction) {
                DB::rollBack();
            }
            return $this->errorNotFound($th->getMessage(), $th);
        } catch (CreateException $th) {
            if ($useTransaction) {
                DB::rollBack();
            }
            return $this->errorBadRequest($th->getMessage(), $th);
        } catch (\Exception $th) {
            if ($useTransaction) {
                DB::rollBack();
            }
            return $this->errorBadRequest($th->getMessage(), $th);
        }
    }
}
