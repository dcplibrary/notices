
CREATE VIEW CircReserveItemRecords_View
AS
--	18-Jul-2006		Tom Lafave
--		Included the new ShelvingBit column from CircItemRecords in the
--		view for Find Tool 'Shelving' status display
--
--12/15/2003	BRW
--		Created view to facilitate processing CircItemRecords that
--		are on reserve.  When an item record is on reserve, certain
--		values from the ReserveItemRecord override the CircItemRecords.

SELECT
	--CIR
	cir.ItemRecordID,
	cir.Barcode,
	cir.ItemStatusID,
	cir.LastCircTransactionDate,
	cir.AssociatedBibRecordID,
	cir.ParentItemRecordID, 
	cir.RecordStatusID,
	cir.MaterialTypeID,
	cir.LastUsePatronID,
	cir.LastUseBranchID, 
	cir.YTDCircCount,
	COALESCE(pyic.YTDCircCount,0) AS N'YTDPrevCircCount',
	cir.LifetimeCircCount,
	cir.YTDInHouseUseCount, 
	COALESCE(pyic.YTDInHouseUseCount,0) AS N'YTDPrevInHouseUseCount',
	cir.LifetimeInHouseUseCount,
	cir.FreeTextBlock,
	cir.ManualBlockID, 
	cir.StatisticalCodeID, 
	cir.ILLFlag,
	cir.DisplayInPAC, 
	cir.LoanableOutsideSystem, 
	cir.NonCirculating,
	cir.RecordStatusDate, 
	cir.LastCircWorkstationID,
	cir.LastCircPolarisUserID,
	cir.OriginalCheckOutDate,
	cir.OriginalDueDate,
	cir.ItemStatusDate,
	cir.CheckInBranchID,
	cir.CheckInWorkstationID,
	cir.CheckInUserID,
	cir.CheckInDate,
	cir.InTransitSentBranchID,
	cir.InTransitSentDate,
	cir.InTransitRecvdBranchID,
	cir.InTransitRecvdDate,
	cir.LastCheckOutRenewDate,
	--These fields are common between the CIR and RIR.  Use the RIR value when available (and licensed and reserved)
	case when (rir.IsReserved = 1 and rir.ReserveAssignedBranchID is not null) then rir.ReserveAssignedBranchID else cir.AssignedBranchID end as AssignedBranchID, 
	case when (rir.IsReserved = 1 and rir.ReserveAssignedCollectionID is not null) then rir.ReserveAssignedCollectionID else cir.AssignedCollectionID end as AssignedCollectionID, 
	case when (rir.IsReserved = 1 and rir.ReserveFineCodeID is not null) then rir.ReserveFineCodeID else cir.FineCodeID end as FineCodeID,
	case when (rir.IsReserved = 1 and rir.ReserveLoanPeriodCodeID is not null) then rir.ReserveLoanPeriodCodeID else cir.LoanPeriodCodeID end as LoanPeriodCodeID,
	case when (rir.IsReserved = 1 and rir.ReserveShelfLocationID is not null) then rir.ReserveShelfLocationID else cir.ShelfLocationID end as ShelfLocationID,
	case when (rir.IsReserved = 1 and rir.ReserveRenewalLimit is not null) then rir.ReserveRenewalLimit else cir.RenewalLimit end as RenewalLimit,
	case when (rir.IsReserved = 1 and rir.ReserveHoldable is not null) then rir.ReserveHoldable else cir.Holdable end as Holdable,
	--These are CIR only fields but are overridden when the item is a reserve item
	case when (rir.IsReserved = 1 and rir.ReserveHoldable is not null) then 0 else cir.HoldableByPickup end as HoldableByPickup,
	case when (rir.IsReserved = 1 and rir.ReserveHoldable is not null) then 0 else cir.HoldPickupBranchID end as HoldPickupBranchID,
	case when (rir.IsReserved = 1 and rir.ReserveHoldable is not null) then 0 else cir.HoldableByBranch end as HoldableByBranch,
	case when (rir.IsReserved = 1 and rir.ReserveHoldable is not null) then 0 else cir.HoldableByLibrary end as HoldableByLibrary,
	case when (rir.IsReserved = 1 and rir.ReserveHoldable is not null) then 0 else cir.HoldableByPrimaryLender end as HoldableByPrimaryLender,
	--RIR
	rir.CopyrightFeeRequired,
	rir.CopyrightCompliance,
	rir.CopyrightFee, 
	rir.CopyrightFeePaid,
	rir.LimitedToLibUse,
	rir.LibraryCopy, 
	rir.DeleteWhenDone,
	rir.ActionWhenDone,
	rir.RequiresFullCataloging, 
	rir.Purchase,
	rir.YTDCircCount AS ReserveYTDCircCount, 
	rir.Note,
	rir.IsReserved,
	case when (i.IssueID is null) then 1 else ISNULL(i.Retained, 0) end as Retained,	-- if no issue links, then always retained
	cir.ShelvingBit,
	cir.FirstAvailableDate,
	cir.LoaningOrgID,
	cir.HomeBranchID,
	cir.ItemDoesNotFloat,
	cir.DoNotMailToPatron,
	cir.ElectronicItem,
	cir.ResourceEntityID,
	cir.DelayedHoldsFlag,
	cir.DelayedNumberOfDays
FROM
	Polaris.CircItemRecords cir WITH (NOLOCK)
	LEFT OUTER JOIN Polaris.ReserveItemRecords rir WITH (NOLOCK)
		ON (cir.ItemRecordID = rir.ItemRecordID)
	LEFT OUTER JOIN Polaris.MfhdIssues i WITH (NOLOCK) 
		ON (cir.ItemRecordID = i.ItemRecordID)
	LEFT OUTER JOIN Polaris.PrevYearItemsCirc pyic WITH (NOLOCK)
		ON (cir.ItemRecordID = pyic.ItemRecordID)
